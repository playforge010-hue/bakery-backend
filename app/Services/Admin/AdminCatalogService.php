<?php

namespace App\Services\Admin;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductCustomizationOption;
use App\Models\ProductImage;
use App\Models\ProductInventory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Coordinate catalog persistence, relation replacement, media paths, and stock updates. */
class AdminCatalogService
{
    /** Persist localized category fields and optional configured images. */
    public function saveCategory(Request $request, array $data): ProductCategory
    {
        $id = $data['id'] ?? null;
        unset($data['id']);
        $previousImages = $id
            ? ProductCategory::query()->whereKey($id)->firstOrFail()->only(['image_path', 'banner_image_path'])
            : ['image_path' => null, 'banner_image_path' => null];

        $model = DB::transaction(function () use ($request, $data, $id) {
            $record = $id ? ProductCategory::query()->findOrFail($id) : new ProductCategory;

            $parentId = $data['parent_id'] ?? null;
            $visited = [];
            while ($parentId) {
                if ($parentId == $id || in_array($parentId, $visited, true)) {
                    throw ValidationException::withMessages(['parent_id' => ['Choose a parent outside this category branch.']]);
                }
                $visited[] = $parentId;
                $parentId = ProductCategory::query()->findOrFail($parentId)->parent_id;
            }
            if ($data['remove_image'] ?? false) {
                $data['image_path'] = null;
            }
            if ($data['remove_banner_image'] ?? false) {
                $data['banner_image_path'] = null;
            }
            unset($data['remove_image'], $data['remove_banner_image'], $data['image'], $data['banner_image']);

            if ($request->hasFile('image')) {
                $categoryImageDirectory = config('imagepath.directories.categories');
                $imageDisk = config('imagepath.disk');
                $data['image_path'] = $request->file('image')->store(
                    $categoryImageDirectory,
                    $imageDisk
                );
            }

            if ($request->hasFile('banner_image')) {
                $bannerImageDirectory = config('imagepath.directories.categories');
                $imageDisk = config('imagepath.disk');
                $data['banner_image_path'] = $request->file('banner_image')->store(
                    $bannerImageDirectory,
                    $imageDisk
                );
            }

            if (isset($data['name_translations']) && is_array($data['name_translations'])) {
                $categoryName = $data['name_translations']['en']
                    ?: ($data['name_translations']['gu'] ?? $data['name_translations']['hi'] ?? '');
                $data['slug'] ??= $record->slug ?: (Str::slug($categoryName) ?: 'category-'.Str::lower(Str::random(10)));
            }
            $record->fill($data)->save();

            return $record->refresh();
        });

        $imageDisk = config('imagepath.disk');
        foreach (['image_path', 'banner_image_path'] as $imageColumn) {
            $previousPath = $previousImages[$imageColumn] ?? null;
            $currentPath = $model->{$imageColumn};

            if ($previousPath && $previousPath !== $currentPath) {
                Storage::disk($imageDisk)->delete($previousPath);
            }
        }

        return $model;
    }

    /** Persist product fields, options, inventory, and configured media atomically. */
    public function saveProduct(Request $request, array $data): Product
    {
        $id = $data['id'] ?? null;
        unset($data['id']);

        return DB::transaction(function () use ($request, $data, $id) {
            $product = $id ? Product::query()->findOrFail($id) : new Product;

            if ($request->hasFile('main_image')) {
                $productImageDirectory = config('imagepath.directories.products');
                $imageDisk = config('imagepath.disk');
                $data['main_image_path'] = $request->file('main_image')->store(
                    $productImageDirectory,
                    $imageDisk
                );
            }

            if (isset($data['name_translations']) && is_array($data['name_translations'])) {
                $productName = ($data['name_translations']['en'] ?? '')
                    ?: ($data['name_translations']['gu'] ?? $data['name_translations']['hi'] ?? '');
                $data['slug'] ??= $product->slug ?: (Str::slug($productName) ?: 'product-'.Str::lower(Str::random(10)));
            }
            $variants = $data['variants'] ?? null;
            $images = $data['images'] ?? null;
            $inventory = $data['inventory'] ?? null;
            $flavours = $data['flavour_ids'] ?? null;
            $addons = $data['addons'] ?? null;
            $customization = $data['customization'] ?? null;
            unset(
                $data['variants'],
                $data['images'],
                $data['inventory'],
                $data['flavour_ids'],
                $data['addons'],
                $data['customization']
            );

            $product->fill($data)->save();
            if (is_array($variants)) {
                $retainedVariantIds = [];
                if (collect($variants)->where('is_default', true)->count() > 1) {
                    throw ValidationException::withMessages(['variants' => ['Choose only one default size.']]);
                }

                $product->variants()->update(['is_default' => false]);

                foreach ($variants as $sortOrder => $variant) {
                    $variant['sort_order'] = $sortOrder;
                    $variantId = $variant['id'] ?? null;
                    unset($variant['id']);
                    if ($variantId) {
                        $record = $product->variants()->findOrFail($variantId);
                        $record->fill($variant)->save();
                    } else {
                        $record = $product->variants()->create($variant);
                    }

                    $retainedVariantIds[] = $record->id;
                }

                $product->variants()
                    ->whereNotIn('id', $retainedVariantIds ?: [0])
                    ->update(['is_active' => false, 'is_default' => false]);
            }
            if (is_array($images)) {
                foreach ($images as $index => $image) {
                    if (is_string($image) && $image !== '') {
                        ProductImage::create([
                            'product_id' => $product->id,
                            'image_path' => $image,
                            'sort_order' => $index,
                        ]);
                    }
                }
            }
            if (is_array($inventory)) {
                $variantId = $inventory['product_variant_id'] ?? null;
                if ($variantId && ! $product->variants()->whereKey($variantId)->exists()) {
                    abort(422, 'The selected variant does not belong to this product.');
                }
                ProductInventory::query()->updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'product_variant_id' => $variantId,
                    ],
                    $inventory
                );
            }
            if (is_array($flavours)) {
                $product->flavours()->sync($flavours);
            }
            if (is_array($addons)) {
                $addonOptions = collect($addons)->mapWithKeys(fn ($addon) => [
                    $addon['id'] => [
                        'is_required' => (bool) ($addon['is_required'] ?? false),
                        'max_quantity' => $addon['max_quantity'] ?? null,
                    ],
                ]);
                $product->addons()->sync($addonOptions->all());
            }
            if (is_array($customization)) {
                ProductCustomizationOption::query()->updateOrCreate(['product_id' => $product->id], $customization);
            }

            return $product->load(['category', 'images', 'variants', 'inventory', 'flavours', 'addons', 'customization']);
        });
    }

    /** Set customer availability for one product and return its persisted state. */
    public function setAvailability(int $id, bool $available, ?string $until = null): Product
    {
        $product = Product::query()->findOrFail($id);
        $product->is_available = $available;
        $product->sold_out_until = $available ? null : $until;
        $product->save();

        return $product;
    }
}
