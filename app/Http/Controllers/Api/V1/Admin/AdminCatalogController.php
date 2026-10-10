<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Admin\WriteAdminAuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\AdminCatalogActionRequest;
use App\Http\Requests\Api\V1\Admin\AdminCategoryRequest;
use App\Http\Requests\Api\V1\Admin\AdminProductOptionRequest;
use App\Http\Requests\Api\V1\Admin\AdminProductRequest;
use App\Models\Product;
use App\Models\ProductAddon;
use App\Models\ProductCategory;
use App\Models\ProductFlavour;
use App\Models\ProductImage;
use App\Models\ProductInventory;
use App\Services\Admin\AdminCatalogService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AdminCatalogController extends Controller
{
    use ApiResponseTrait;

    /** List categories for the administration table with product counts and frontend image URLs. */
    public function getCategoryList(AdminCatalogActionRequest $request): JsonResponse
    {
        $perPage = min((int) $request->integer('per_page', 30), 100);
        $rows = ProductCategory::query()
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($perPage);

        return $this->successResponse('Categories loaded.', $rows->through(fn ($c) => [
            'id' => $c->id,
            'name_translations' => $c->name_translations,
            'description_translations' => $c->description_translations,
            'slug' => $c->slug,
            'image_url' => $c->image_url,
            'banner_image_url' => $c->banner_image_url,
            'parent_id' => $c->parent_id,
            'is_active' => $c->is_active,
            'is_featured' => $c->is_featured,
            'sort_order' => $c->sort_order,
            'products_count' => $c->products_count,
        ]), [
            'current_page' => $rows->currentPage(),
            'last_page' => $rows->lastPage(),
            'total' => $rows->total(),
        ]);
    }

    /** Create or update a category through validated fields and configured image storage. */
    public function saveProductCategory(AdminCategoryRequest $request, AdminCatalogService $service, WriteAdminAuditLog $audit): JsonResponse
    {
        $record = $service->saveCategory($request, $request->validated());
        $audit->execute($request, 'category.saved', $record);

        return $this->successResponse('Category saved.', [
            'id' => $record->id,
            'name_translations' => $record->name_translations,
            'slug' => $record->slug,
            'image_url' => $record->image_url,
            'is_active' => $record->is_active,
        ]);
    }

    /** Create a category; route-compatible alias for the shared category save action. */
    public function createCategory(AdminCategoryRequest $request, AdminCatalogService $service, WriteAdminAuditLog $audit): JsonResponse
    {
        return $this->saveProductCategory($request, $service, $audit);
    }

    /** Update a category; route-compatible alias for the shared category save action. */
    public function updateCategory(AdminCategoryRequest $request, AdminCatalogService $service, WriteAdminAuditLog $audit): JsonResponse
    {
        return $this->saveProductCategory($request, $service, $audit);
    }

    /** Soft-delete a category only when it has no products, retaining recoverability. */
    public function deleteCategory(AdminCatalogActionRequest $request, WriteAdminAuditLog $audit): JsonResponse
    {
        $data = $request->validated();
        $category = ProductCategory::query()->findOrFail($data['id']);

        if ($category->products()->exists()) {
            return $this->errorResponse('Move or remove this category products before deleting it.', [], [], 422);
        }

        $category->delete();
        $audit->execute($request, 'category.deleted', $category);

        return $this->successResponse('Category deleted.', []);
    }

    /** Change category visibility without removing its products. */
    public function updateCategoryStatus(AdminCatalogActionRequest $request, WriteAdminAuditLog $audit): JsonResponse
    {
        $data = $request->validated();
        $category = ProductCategory::query()->findOrFail($data['id']);
        $category->is_active = $data['is_active'];
        $category->save();
        $audit->execute(
            $request,
            'category.status_changed',
            $category,
            ['is_active' => $category->is_active]
        );

        return $this->successResponse('Category status updated.', [
            'id' => $category->id,
            'is_active' => $category->is_active,
        ]);
    }

    /** List products with category, inventory, variant summaries, and configured image URLs. */
    public function getProductList(AdminCatalogActionRequest $request): JsonResponse
    {
        $query = Product::query()
            ->with([
                'category:id,name_translations',
                'inventory',
                'variants',
                'flavours',
                'addons',
                'customization',
                'images',
            ])
            ->when(
                $request->filled('search'),
                fn ($query) => $query->where(function ($searchQuery) use ($request) {
                    $searchTerm = '%'.$request->string('search').'%';

                    $searchQuery->where('slug', 'like', $searchTerm)
                        ->orWhere('sku', 'like', $searchTerm);
                })
            )
            ->when(
                $request->filled('category_id'),
                fn ($query) => $query->where(
                    'product_category_id',
                    $request->integer('category_id')
                )
            )
            ->when(
                $request->has('is_available'),
                fn ($query) => $query->where(
                    'is_available',
                    $request->boolean('is_available')
                )
            )
            ->orderBy('sort_order')
            ->latest('id');
        $perPage = min((int) $request->integer('per_page', 30), 100);
        $rows = $query->paginate($perPage);

        $products = $rows->through(
            fn (Product $product) => $this->formatProductForAdmin($product)
        );

        return $this->successResponse('Products loaded.', $products, [
            'current_page' => $rows->currentPage(),
            'last_page' => $rows->lastPage(),
            'total' => $rows->total(),
        ]);
    }

    /** Shape the product fields and related options used by the administration editor. */
    private function formatProductForAdmin(Product $product): array
    {
        return [
            'id' => $product->id,
            'category_id' => $product->product_category_id,
            'product_category_id' => $product->product_category_id,
            'category' => $product->category?->name_translations,
            'name_translations' => $product->name_translations,
            'short_description_translations' => $product->short_description_translations,
            'description_translations' => $product->description_translations,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'image_url' => $product->main_image_url,
            'images' => $product->images->sortBy('sort_order')->values()->map(fn (ProductImage $image) => [
                'id' => $image->id,
                'image_url' => $image->image_url,
                'is_primary' => $image->is_primary,
                'sort_order' => $image->sort_order,
            ]),
            'base_price' => (float) $product->base_price,
            'min_preparation_minutes' => $product->min_preparation_minutes,
            'max_preparation_minutes' => $product->max_preparation_minutes,
            'product_type' => $product->product_type,
            'food_type' => $product->food_type,
            'is_customizable' => $product->is_customizable,
            'allow_message' => $product->allow_message,
            'allow_photo_upload' => $product->allow_photo_upload,
            'sort_order' => $product->sort_order,
            'is_eggless_available' => $product->is_eggless_available,
            'is_egg_available' => $product->is_egg_available,
            'is_featured' => $product->is_featured,
            'is_bestseller' => $product->is_bestseller,
            'is_new' => $product->is_new,
            'is_available' => $product->is_available,
            'sold_out_until' => $product->sold_out_until?->toIso8601String(),
            'inventory' => $product->inventory?->only([
                'stock_quantity',
                'low_stock_threshold',
                'track_stock',
                'is_sold_out',
                'sold_out_until',
            ]) ?? [
                'stock_quantity' => null,
                'track_stock' => false,
            ],
            'variants' => $product->variants->sortBy('sort_order')->values()->map(fn ($variant) => [
                'id' => $variant->id,
                'name_translations' => $variant->name_translations,
                'sku' => $variant->sku,
                'price' => (float) $variant->price,
                'is_active' => $variant->is_active,
                'is_default' => $variant->is_default,
            ]),
            'flavour_ids' => $product->flavours->pluck('id'),
            'addons' => $product->addons->map(fn ($addon) => [
                'id' => $addon->id,
                'is_required' => $addon->pivot->is_required,
                'max_quantity' => $addon->pivot->max_quantity,
            ]),
            'customization' => $product->customization?->only([
                'allow_message',
                'message_label_translations',
                'message_max_length',
                'allow_photo_upload',
                'photo_upload_label_translations',
                'allow_flavour_selection',
                'allow_egg_selection',
                'allow_delivery_date_selection',
                'min_notice_minutes',
                'max_schedule_days',
            ]),
        ];
    }

    /** Store a product, its variant and inventory attributes, and return its admin-safe summary. */
    public function saveProduct(AdminProductRequest $request, AdminCatalogService $service, WriteAdminAuditLog $audit): JsonResponse
    {
        $product = $service->saveProduct($request, $request->validated());
        $audit->execute($request, 'product.saved', $product);

        $variantSummaries = $product->variants->map(fn ($variant) => [
            'id' => $variant->id,
            'name_translations' => $variant->name_translations,
            'price' => (float) $variant->price,
        ]);

        return $this->successResponse('Product saved.', [
            'id' => $product->id,
            'name_translations' => $product->name_translations,
            'slug' => $product->slug,
            'image_url' => $product->main_image_url,
            'base_price' => (float) $product->base_price,
            'is_available' => $product->is_available,
            'variants' => $variantSummaries,
        ]);
    }

    /** Create a product using the catalog service and explicit product request validation. */
    public function createProduct(AdminProductRequest $request, AdminCatalogService $service, WriteAdminAuditLog $audit): JsonResponse
    {
        return $this->saveProduct($request, $service, $audit);
    }

    /** Update a product using the catalog service and explicit product request validation. */
    public function updateProduct(AdminProductRequest $request, AdminCatalogService $service, WriteAdminAuditLog $audit): JsonResponse
    {
        return $this->saveProduct($request, $service, $audit);
    }

    /** Change a product's category without exposing the database model. */
    public function updateProductCategory(AdminCatalogActionRequest $request, WriteAdminAuditLog $audit): JsonResponse
    {
        $d = $request->validated();
        $p = Product::query()->findOrFail($d['product_id']);
        $p->product_category_id = $d['category_id'];
        $p->save();
        $audit->execute($request, 'product.category_changed', $p, ['category_id' => $p->product_category_id]);

        return $this->successResponse('Product category updated.', ['id' => $p->id, 'category_id' => $p->product_category_id]);
    }

    /** Provide the portal's status action while preserving the standard availability contract. */
    public function updateProductStatus(AdminCatalogActionRequest $request, AdminCatalogService $service, WriteAdminAuditLog $audit): JsonResponse
    {
        return $this->updateProductAvailability($request, $service, $audit);
    }

    /** Soft-delete an unused product record after audit logging. */
    public function deleteProduct(AdminCatalogActionRequest $request, WriteAdminAuditLog $audit): JsonResponse
    {
        $d = $request->validated();
        $p = Product::query()->findOrFail($d['id']);
        $p->delete();
        $audit->execute($request, 'product.deleted', $p);

        return $this->successResponse('Product deleted.', []);
    }

    /** Upload a gallery photo through the transactional media service. */
    public function uploadProductImage(
        AdminCatalogActionRequest $request,
        \App\Services\Admin\AdminProductMediaService $media,
        WriteAdminAuditLog $audit
    ): JsonResponse {
        $image = $media->upload($request->file('image'), $request->validated());
        $audit->execute($request, 'product.image_uploaded', $image);

        return $this->successResponse('Product image uploaded.', [
            'id' => $image->id,
            'image_url' => $image->image_url,
            'is_primary' => $image->is_primary,
            'sort_order' => $image->sort_order,
        ], [], 201);
    }

    /** Delete a gallery photo and choose a replacement main image when needed. */
    public function deleteProductImage(
        AdminCatalogActionRequest $request,
        \App\Services\Admin\AdminProductMediaService $media,
        WriteAdminAuditLog $audit
    ): JsonResponse {
        $image = $media->remove($request->validated()['image_id']);
        $audit->execute($request, 'product.image_deleted', $image);

        return $this->successResponse('Product image deleted.', []);
    }

    /** Save the complete gallery order and exactly one main image. */
    public function updateProductImageOrder(
        AdminCatalogActionRequest $request,
        \App\Services\Admin\AdminProductMediaService $media,
        WriteAdminAuditLog $audit
    ): JsonResponse {
        $product = $media->reorder($request->validated());
        $audit->execute($request, 'product.images_reordered', $product);

        return $this->successResponse('Product photo order saved.', []);
    }

    /** Mark a product sold out or restore availability, optionally with an expiry timestamp. */
    public function updateProductAvailability(AdminCatalogActionRequest $request, AdminCatalogService $service, WriteAdminAuditLog $audit): JsonResponse
    {
        $d = $request->validated();
        $p = $service->setAvailability($d['id'], $d['is_available'], $d['sold_out_until'] ?? null);
        $audit->execute($request, 'product.availability_changed', $p, ['is_available' => $p->is_available, 'sold_out_until' => $p->sold_out_until]);

        return $this->successResponse('Product availability updated.', [
            'id' => $p->id,
            'is_available' => $p->is_available,
            'sold_out_until' => $p->sold_out_until?->toIso8601String(),
        ]);
    }

    /** Replace a product stock quantity and threshold with a transactional inventory update. */
    public function updateProductStock(AdminCatalogActionRequest $request, WriteAdminAuditLog $audit): JsonResponse
    {
        $d = $request->validated();
        $stock = DB::transaction(fn () => ProductInventory::query()->updateOrCreate([
            'product_id' => $d['product_id'],
            'product_variant_id' => $d['product_variant_id'] ?? null,
        ], $d));
        if (isset($d['is_sold_out'])) {
            Product::query()->whereKey($d['product_id'])->update(['is_available' => ! $d['is_sold_out']]);
        }

        $audit->execute($request, 'product.stock_changed', $stock, [
            'stock_quantity' => $stock->stock_quantity,
        ]);

        return $this->successResponse('Stock updated.', [
            'product_id' => $stock->product_id,
            'product_variant_id' => $stock->product_variant_id,
            'stock_quantity' => $stock->stock_quantity,
            'low_stock_threshold' => $stock->low_stock_threshold,
            'track_stock' => $stock->track_stock,
            'is_sold_out' => $stock->is_sold_out,
        ]);
    }

    /** List reusable cake flavours and their surcharge values. */
    public function getFlavourList(): JsonResponse
    {
        return $this->successResponse('Flavours loaded.', ProductFlavour::query()->orderBy('sort_order')->get([
            'id',
            'name_translations',
            'description_translations',
            'extra_price',
            'is_active',
            'sort_order',
        ]));
    }

    /** Create or update a reusable flavour definition. */
    public function saveFlavour(AdminProductOptionRequest $request, WriteAdminAuditLog $audit): JsonResponse
    {
        $data = $request->validated();
        $id = $data['id'] ?? null;
        unset($data['id']);
        $flavour = ProductFlavour::query()->updateOrCreate(['id' => $id], $data);
        $audit->execute($request, 'catalog.flavour_saved', $flavour);

        return $this->successResponse('Flavour saved.', [
            'id' => $flavour->id,
            'name_translations' => $flavour->name_translations,
            'extra_price' => (float) $flavour->extra_price,
            'is_active' => $flavour->is_active,
        ]);
    }

    /** Create a reusable product flavour definition. */
    public function createFlavour(AdminProductOptionRequest $request, WriteAdminAuditLog $audit): JsonResponse
    {
        return $this->saveFlavour($request, $audit);
    }

    /** Update a reusable product flavour definition. */
    public function updateFlavour(AdminProductOptionRequest $request, WriteAdminAuditLog $audit): JsonResponse
    {
        return $this->saveFlavour($request, $audit);
    }

    /** List reusable add-ons with configured image URLs and prices. */
    public function getAddonList(): JsonResponse
    {
        return $this->successResponse('Add-ons loaded.', ProductAddon::query()->orderBy('sort_order')->get()->map(fn ($a) => [
            'id' => $a->id,
            'name_translations' => $a->name_translations,
            'description_translations' => $a->description_translations,
            'image_url' => $a->image_url,
            'price' => (float) $a->price,
            'is_active' => $a->is_active,
            'sort_order' => $a->sort_order,
        ]));
    }

    /** Create or update an add-on and save its image under the configured catalog path. */
    public function saveAddon(AdminProductOptionRequest $request, WriteAdminAuditLog $audit): JsonResponse
    {
        $data = $request->validated();
        $id = $data['id'] ?? null;
        unset($data['id']);
        if ($data['remove_image'] ?? false) {
            $data['image_path'] = null;
        }
        unset($data['remove_image'], $data['image']);
        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store(config('imagepath.directories.products'), config('imagepath.disk'));
        }

        $addon = ProductAddon::query()->updateOrCreate(['id' => $id], $data);
        $audit->execute($request, 'catalog.addon_saved', $addon);

        return $this->successResponse('Add-on saved.', [
            'id' => $addon->id,
            'name_translations' => $addon->name_translations,
            'image_url' => $addon->image_url,
            'price' => (float) $addon->price,
            'is_active' => $addon->is_active,
        ]);
    }

    /** Create a reusable product add-on and optional catalog image. */
    public function createAddon(AdminProductOptionRequest $request, WriteAdminAuditLog $audit): JsonResponse
    {
        return $this->saveAddon($request, $audit);
    }

    /** Update a reusable product add-on and optional catalog image. */
    public function updateAddon(AdminProductOptionRequest $request, WriteAdminAuditLog $audit): JsonResponse
    {
        return $this->saveAddon($request, $audit);
    }
}
