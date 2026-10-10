<?php

namespace App\Services\Admin;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/** Coordinate gallery records and the product's main image inside one locked transaction. */
class AdminProductMediaService
{
    /** Store an uploaded image and promote it when chosen or when it is the first gallery photo. */
    public function upload(UploadedFile $file, array $data): ProductImage
    {
        $path = $file->store(config('imagepath.directories.product_gallery'), config('imagepath.disk'));
        try {
            return DB::transaction(function () use ($data, $path): ProductImage {
                $product = Product::query()->lockForUpdate()->findOrFail($data['product_id']);
                $primary = (bool) ($data['is_primary'] ?? false) || ! $product->images()->exists();
                if ($primary) {
                    $product->images()->update(['is_primary' => false]);
                    $product->forceFill(['main_image_path' => $path])->save();
                }

                return $product->images()->create([
                    'image_path' => $path,
                    'alt_text_translations' => $data['alt_text_translations'] ?? null,
                    'sort_order' => $data['sort_order'] ?? ((int) $product->images()->max('sort_order') + 1),
                    'is_primary' => $primary,
                ]);
            });
        } catch (\Throwable $exception) {
            Storage::disk(config('imagepath.disk'))->delete($path);
            throw $exception;
        }
    }

    /** Remove one gallery record and select the earliest remaining photo when deleting the main photo. */
    public function remove(int $imageId): ProductImage
    {
        $image = DB::transaction(function () use ($imageId): ProductImage {
            $image = ProductImage::query()->findOrFail($imageId);
            $product = Product::query()->lockForUpdate()->findOrFail($image->product_id);
            if ($image->is_primary || $product->main_image_path === $image->image_path) {
                $replacement = $product->images()->whereKeyNot($image->id)->orderBy('sort_order')->orderBy('id')->first();
                $product->images()->update(['is_primary' => false]);
                $replacement?->forceFill(['is_primary' => true])->save();
                $product->forceFill(['main_image_path' => $replacement?->image_path])->save();
            }
            $image->delete();

            return $image;
        });
        Storage::disk(config('imagepath.disk'))->delete($image->image_path);

        return $image;
    }

    /** Reorder the complete gallery and enforce exactly one primary image belonging to this product. */
    public function reorder(array $data): Product
    {
        return DB::transaction(function () use ($data): Product {
            $product = Product::query()->lockForUpdate()->findOrFail($data['product_id']);
            $images = collect($data['images']);
            $ids = $product->images()->pluck('id')->sort()->values()->all();
            $submitted = $images->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            if ($ids !== $submitted || $images->where('is_primary', true)->count() !== 1) {
                throw ValidationException::withMessages(['images' => ['Reload the gallery and choose exactly one main photo.']]);
            }
            foreach ($images as $imageData) {
                $image = $product->images()->findOrFail($imageData['id']);
                $image->fill(['sort_order' => $imageData['sort_order'], 'is_primary' => $imageData['is_primary']])->save();
                if ($image->is_primary) {
                    $product->forceFill(['main_image_path' => $image->image_path])->save();
                }
            }

            return $product;
        });
    }
}
