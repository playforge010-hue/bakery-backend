<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Requests\Api\V1\Customer\CustomerProductDetailRequest;
use App\Http\Requests\Api\V1\Customer\CustomerProductListRequest;
use App\Http\Resources\Api\V1\Customer\CustomerBannerResource;
use App\Models\Banner;
use App\Models\BusinessSetting;
use App\Support\ImagePath;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/** Customer-facing catalog API operations. */
class CustomerCatalogController extends CustomerApiControllerBase
{
    /** Return public bakery settings, feature flags, and languages. */
    public function getAppBootstrap(): JsonResponse
    {
        $business = DB::table('business_settings')->first();

        return $this->successResponse('App bootstrap loaded successfully.', [
            'business' => [
                'name' => $business?->bakery_name,
                'city' => $business?->city,
                'phone' => $business?->phone_number,
                'whatsapp' => $business?->whatsapp_number,
                'address' => trim(implode(', ', array_filter([
                    $business?->address_line_1,
                    $business?->address_line_2,
                    $business?->area,
                    $business?->city,
                    $business?->state,
                    $business?->pincode,
                ]))),
                'latitude' => $business?->latitude !== null ? (float) $business->latitude : null,
                'longitude' => $business?->longitude !== null ? (float) $business->longitude : null,
                'minimum_order_amount' => (float) ($business?->minimum_order_amount ?? 0),
            ],
            'features' => [
                'delivery_available' => (bool) ($business?->delivery_available ?? false),
                'takeaway_available' => (bool) ($business?->takeaway_available ?? false),
                'otp_login_enabled' => (string) config('services.customer_otp.test_mobile_number', '') !== '',
                // Online methods stay hidden until a payment provider can verify capture.
                'online_payment_available' => false,
                'upi_available' => false,
                'cash_on_delivery_available' => (bool) ($business?->cod_available ?? false),
                'schedule_order_available' => true,
            ],
            'languages' => [
                ['code' => 'gu', 'label' => 'Gujarati', 'is_default' => true],
                ['code' => 'en', 'label' => 'English', 'is_default' => false],
                ['code' => 'hi', 'label' => 'Hindi', 'is_default' => false],
            ],
        ]);
    }

    /** Return admin-managed bakery, promotion and catalog data for the storefront. */
    public function getHomeData(): JsonResponse
    {
        $business = BusinessSetting::query()->first();
        $banners = Banner::query()
            ->currentlyActive()
            ->orderBy('sort_order')
            ->get(['id', 'title_translations', 'subtitle_translations', 'image_path', 'link_type', 'link_value'])
            ->map(fn (Banner $banner) => CustomerBannerResource::make($banner)->resolve())
            ->values();

        return $this->successResponse('Home data loaded successfully.', [
            'business' => [
                'name' => $business?->bakery_name,
                'city' => $business?->city,
                'area' => $business?->area,
                'address' => trim(implode(', ', array_filter([
                    $business?->address_line_1,
                    $business?->area,
                    $business?->city,
                    $business?->state,
                ]))),
                'logo_url' => $business?->logo_url,
                'phone' => $business?->phone_number,
                'whatsapp' => $business?->whatsapp_number,
                'social_links' => $business?->social_links_json ?? [],
                'delivery_available' => (bool) ($business?->delivery_available ?? false),
                'takeaway_available' => (bool) ($business?->takeaway_available ?? false),
                'max_delivery_distance_km' => (float) ($business?->max_delivery_distance_km ?? 0),
            ],
            'banners' => $banners,
            'categories' => $this->catalog->categoryCards(),
            'featured_products' => $this->catalog->productCards(['featured' => true]),
            'delivery_areas' => collect([$business?->area, $business?->city])
                ->filter()
                ->unique()
                ->values()
                ->map(fn ($name, $index) => ['id' => $index + 1, 'name' => $name])
                ->all(),
        ]);
    }

    /** Return active customer-facing categories. */
    public function getCategoryList(): JsonResponse
    {
        return $this->successResponse('Category list loaded successfully.', $this->catalog->categoryCards());
    }

    /** Return active, currently redeemable offers for the mobile storefront. */
    public function getMobileOfferList(): JsonResponse
    {
        $offers = DB::table('coupons')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->where(function ($query) {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->orderBy('starts_at')
            ->get()
            ->map(fn ($offer) => [
                'id' => $offer->id,
                'code' => $offer->code,
                'title' => $this->catalog->translated($offer->title_translations),
                'description' => $this->catalog->translated($offer->description_translations),
                'discount_type' => $offer->discount_type,
                'discount_value' => (float) $offer->discount_value,
                'maximum_discount' => $offer->max_discount_amount !== null
                    ? (float) $offer->max_discount_amount
                    : null,
                'minimum_order_amount' => $offer->minimum_order_amount !== null
                    ? (float) $offer->minimum_order_amount
                    : null,
                'applies_to' => $offer->applies_to_fulfillment_type,
                'expires_at' => $offer->ends_at,
            ])
            ->values();

        return $this->successResponse('Offer list loaded successfully.', $offers);
    }

    /** Return available products filtered by the supplied query. */
    public function getProductList(CustomerProductListRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $isPaginated = $request->has('page') || $request->has('per_page');
        $products = $isPaginated
            ? $this->catalog->paginatedProductCards($filters, (int) ($filters['per_page'] ?? 12))
            : null;

        return $this->successResponse(
            'Product list loaded successfully.',
            $products['items'] ?? $this->catalog->productCards($filters),
            array_filter([
                'filters' => [
                    'search' => $filters['search'] ?? null,
                    'category' => $filters['category'] ?? null,
                ],
                'pagination' => $products['pagination'] ?? null,
            ], fn ($value) => $value !== null)
        );
    }

    /** Return one product with its customer customization options. */
    public function getProductDetail(CustomerProductDetailRequest $request): JsonResponse
    {
        $slug = $request->validated('slug');
        $product = DB::table('products')->where('slug', $slug)->whereNull('deleted_at')->first();

        if (! $product) {
            return $this->errorResponse('Product not found.', [], [], 404);
        }

        $variants = DB::table('product_variants')
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($variant) => [
                'id' => $variant->id,
                'label' => $this->catalog->translated($variant->name_translations),
                'price' => (float) $variant->price,
            ])
            ->values();

        $flavours = DB::table('product_flavour_options')
            ->join('product_flavours', 'product_flavours.id', '=', 'product_flavour_options.product_flavour_id')
            ->where('product_flavour_options.product_id', $product->id)
            ->orderBy('product_flavours.sort_order')
            ->get()
            ->map(fn ($flavour) => $this->catalog->translated($flavour->name_translations))
            ->values();

        $addons = DB::table('product_addon_options')
            ->join('product_addons', 'product_addons.id', '=', 'product_addon_options.product_addon_id')
            ->where('product_addon_options.product_id', $product->id)
            ->orderBy('product_addons.sort_order')
            ->get()
            ->map(fn ($addon) => [
                'id' => $addon->id,
                'name' => $this->catalog->translated($addon->name_translations),
                'price' => (float) $addon->price,
            ])
            ->values();

        $customization = DB::table('product_customization_options')->where('product_id', $product->id)->first();

        return $this->successResponse('Product detail loaded successfully.', [
            'id' => $product->id,
            'name' => $this->catalog->translated($product->name_translations),
            'slug' => $product->slug,
            'rating' => null,
            'price' => (float) $product->base_price,
            'price_text' => 'From Rs. '.number_format((float) $product->base_price, 0),
            'preparation_time_minutes' => $product->min_preparation_minutes,
            'image_url' => ImagePath::url($product->main_image_path),
            'gallery_urls' => DB::table('product_images')
                ->where('product_id', $product->id)
                ->orderBy('sort_order')
                ->pluck('image_path')
                ->map(fn (string $path) => ImagePath::url($path))
                ->values(),
            'variants' => $variants,
            'flavours' => $flavours,
            'egg_options' => array_values(array_filter([
                $product->is_eggless_available ? 'Eggless' : null,
                $product->is_egg_available ? 'With Egg' : null,
            ])),
            'addons' => $addons,
            'customization' => [
                'allow_message' => (bool) ($customization?->allow_message ?? false),
                'message_max_length' => $customization?->message_max_length,
                'allow_photo_upload' => (bool) ($customization?->allow_photo_upload ?? false),
                'allow_schedule' => (bool) ($customization?->allow_delivery_date_selection ?? false),
            ],
        ]);
    }
}
