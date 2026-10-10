<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Actions\Customer\AddConfiguredProductToCart;
use App\Actions\Customer\PlaceCustomerOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\AddConfiguredProductRequest;
use App\Http\Requests\Api\V1\Customer\AvailableSchedulesRequest;
use App\Http\Requests\Api\V1\Customer\CheckoutAvailabilityRequest;
use App\Http\Requests\Api\V1\Customer\CheckoutRequest;
use App\Http\Requests\Api\V1\Customer\ProductOrderOptionsRequest;
use App\Http\Resources\Api\V1\Customer\CheckoutSummaryResource;
use App\Models\Product;
use App\Services\CustomerCheckoutService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/** Customer product configuration, checkout pricing, schedule availability and order submission. */
class CustomerCheckoutController extends Controller
{
    use ApiResponseTrait;

    /** Inject checkout calculations shared by customer checkout endpoints. */
    public function __construct(private readonly CustomerCheckoutService $checkout)
    {
    }

    /** Return active product configuration, including category and trusted option identifiers. */
    public function getProductOrderOptions(ProductOrderOptionsRequest $request): JsonResponse
    {
        $product = Product::query()->available()->with([
            'category',
            'variants' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order'),
            'variants.inventory',
            'flavourOptions.flavour',
            'addonOptions.addon',
            'customization',
            'inventory',
            'images',
        ])->where('slug', $request->validated('slug'))->first();
        if (! $product) {
            return $this->errorResponse('Product not found or unavailable.', [], [], 404);
        }
        $variants = $product->variants->map(fn ($variant) => [
            'id' => (int) $variant->id,
            'label' => $this->checkout->translated($variant->name_translations),
            'price' => (float) $variant->price,
            'is_default' => (bool) $variant->is_default,
            'available' => ! $variant->inventory || $variant->inventory->is_available_for_order,
        ]);
        $flavours = $product->flavourOptions
            ->filter(fn ($option) => $option->flavour?->is_active)
            ->sortBy(fn ($option) => $option->flavour->sort_order)
            ->map(fn ($option) => [
                'id' => (int) $option->flavour->id,
                'name' => $this->checkout->translated($option->flavour->name_translations),
                'extra_price' => (float) $option->flavour->extra_price,
                'is_default' => (bool) $option->is_default,
            ])
            ->values();
        $addons = $product->addonOptions
            ->filter(fn ($option) => $option->addon?->is_active)
            ->sortBy(fn ($option) => $option->addon->sort_order)
            ->map(fn ($option) => [
                'id' => (int) $option->addon->id,
                'name' => $this->checkout->translated($option->addon->name_translations),
                'price' => (float) $option->addon->price,
                'max_quantity' => $option->max_quantity,
                'is_required' => (bool) $option->is_required,
            ])
            ->values();
        $customization = $product->customization;
        $inventory = $product->inventory;

        return $this->successResponse('Product options loaded successfully.', [
            'id' => (int) $product->id,
            'slug' => $product->slug,
            'name' => $this->checkout->translated($product->name_translations),
            'category' => $this->checkout->translated($product->category?->name_translations),
            'description' => $this->checkout->translated($product->description_translations),
            'price' => (float) $product->base_price,
            'image_url' => $product->image_url,
            'gallery_urls' => $product->images
                ->sortByDesc('is_primary')
                ->map(fn ($image) => $image->image_url)
                ->values(),
            'preparation_time_minutes' => (int) $product->min_preparation_minutes,
            'variants' => $variants,
            'flavours' => $flavours,
            'addons' => $addons,
            'egg_options' => array_values(array_filter([$product->is_eggless_available ? 'Eggless' : null, $product->is_egg_available ? 'With Egg' : null])),
            'available' => $product->variants->isNotEmpty()
                ? $product->variants->contains(fn ($variant) => ! $variant->inventory || $variant->inventory->is_available_for_order)
                : (! $inventory || $inventory->is_available_for_order),
            'customization' => [
                'allow_message' => (bool) ($customization?->allow_message ?? $product->allow_message),
                'message_max_length' => (int) ($customization?->message_max_length ?? 200),
                'allow_photo_upload' => (bool) ($customization?->allow_photo_upload ?? $product->allow_photo_upload),
                'allow_schedule' => (bool) ($customization?->allow_delivery_date_selection ?? true),
            ],
        ]);
    }

    /** Add a fully configured product using server catalog prices and optional reference upload. */
    public function addConfiguredProductToCart(AddConfiguredProductRequest $request, AddConfiguredProductToCart $action): JsonResponse
    {
        try {
            $line = $action->execute((int) $request->user()->id, $request->validated(), $request->file('custom_image'));

            return $this->successResponse('Configured product added to your cart.', ['cart_item_id' => (int) $line->id], [], 201);
        } catch (ValidationException $exception) {
            return $this->errorResponse('The selected product options are invalid.', $exception->errors(), [], 422);
        }
    }

    /** Return current catalog-priced cart, delivery fee and eligibility flags before confirmation. */
    public function getCheckoutSummary(CheckoutAvailabilityRequest $request): JsonResponse
    {
        $data = $request->validated();
        try {
            $deliveryLatitude = isset($data['delivery_latitude'])
                ? (float) $data['delivery_latitude']
                : null;
            $deliveryLongitude = isset($data['delivery_longitude'])
                ? (float) $data['delivery_longitude']
                : null;
            $summary = $this->checkout->buildSummary(
                (int) $request->user()->id,
                $data['fulfillment_type'],
                $data['customer_address_id'] ?? null,
                $deliveryLatitude,
                $deliveryLongitude
            );

            return $this->successResponse('Checkout summary calculated successfully.', (new CheckoutSummaryResource($summary))->resolve($request));
        } catch (ValidationException $exception) {
            return $this->errorResponse('Checkout could not be validated.', $exception->errors(), [], 422);
        }
    }

    /** Return only schedule slots that fit configured opening hours and remaining capacity. */
    public function getAvailableSchedules(AvailableSchedulesRequest $request): JsonResponse
    {
        $data = $request->validated();

        $schedules = $this->checkout->availableSchedules(
            $data['fulfillment_type'],
            (int) ($data['days'] ?? 7),
            false,
            $request->user()?->id
        );

        return $this->successResponse(
            'Available order times loaded successfully.',
            $schedules
        );
    }

    /** Create the order from server-repriced cart data and return the customer order summary. */
    public function placeCustomerOrder(CheckoutRequest $request, PlaceCustomerOrder $action): JsonResponse
    {
        try {
            $order = $action->execute($request->user(), $request->validated());

            return $this->successResponse('Order placed successfully.', [
                'order_number' => $order->order_number,
                'status' => $order->order_status,
                'payment_status' => $order->payment_status,
                'grand_total' => (float) $order->grand_total,
                'scheduled_for' => $order->scheduled_for?->toIso8601String(),
                'tracking_url' => '/orders/'.$order->order_number,
            ], [], 201);
        } catch (ValidationException $exception) {
            return $this->errorResponse('Order could not be placed.', $exception->errors(), [], 422);
        }
    }
}
