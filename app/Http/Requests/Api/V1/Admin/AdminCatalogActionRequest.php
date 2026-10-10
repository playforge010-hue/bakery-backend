<?php

namespace App\Http\Requests\Api\V1\Admin;

/** Validate stock, availability, taxonomy and gallery operations by their explicit admin action. */
class AdminCatalogActionRequest extends AdminFormRequest
{
    /** Route role middleware authorizes admin access before catalog validation. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> Rules for the catalog action identified by the route path. */
    public function rules(): array
    {
        return match (basename($this->path())) {
            'update-category-status' => ['id' => ['required', 'integer', 'exists:product_categories,id'],
                'is_active' => ['required', 'boolean']],
            'delete-category' => ['id' => ['required', 'integer', 'exists:product_categories,id']],
            'update-product-category' => ['product_id' => [
                'required',
                'integer',
                'exists:products,id',
            ],
                'category_id' => [
                    'required',
                    'integer',
                    'exists:product_categories,id',
                ]],
            'update-product-status' => ['id' => [
                'required',
                'integer',
                'exists:products,id',
            ],
                'is_available' => [
                    'required',
                    'boolean',
                ],
                'sold_out_until' => [
                    'nullable',
                    'date',
                    'after:now',
                ]],
            'delete-product' => ['id' => ['required', 'integer', 'exists:products,id']],
            'upload-product-image' => ['product_id' => [
                'required',
                'integer',
                'exists:products,id',
            ],
                'image' => [
                    'required',
                    'image',
                    'mimes:jpg,jpeg,png,webp',
                    'max:5120',
                ],
                'alt_text_translations' => [
                    'nullable',
                    'array',
                ],
                'is_primary' => [
                    'sometimes',
                    'boolean',
                ],
                'sort_order' => [
                    'sometimes',
                    'integer',
                    'min:0',
                ]],
            'delete-product-image' => ['image_id' => ['required', 'integer', 'exists:product_images,id']],
            'update-product-image-order' => [
                'product_id' => ['required', 'integer', 'exists:products,id'],
                'images' => ['required', 'array', 'min:1'],
                'images.*.id' => ['required', 'integer', 'distinct', 'exists:product_images,id'],
                'images.*.sort_order' => ['required', 'integer', 'min:0'],
                'images.*.is_primary' => ['required', 'boolean'],
            ],
            'update-product-stock' => ['product_id' => [
                'required',
                'integer',
                'exists:products,id',
            ],
                'product_variant_id' => [
                    'nullable',
                    'integer',
                    'exists:product_variants,id',
                ],
                'stock_quantity' => [
                    'required',
                    'integer',
                    'min:0',
                ],
                'low_stock_threshold' => [
                    'sometimes',
                    'nullable',
                    'integer',
                    'min:0',
                ],
                'track_stock' => [
                    'sometimes',
                    'boolean',
                ],
                'is_sold_out' => [
                    'sometimes',
                    'boolean',
                ],
                'sold_out_until' => [
                    'nullable',
                    'date',
                ]],
            'update-banner-status' => ['id' => ['required', 'integer', 'exists:banners,id'],
                'is_active' => ['required', 'boolean']],
            'delete-banner' => ['id' => ['required', 'integer', 'exists:banners,id']],
            default => ['search' => [
                'sometimes',
                'string',
                'max:190',
            ],
                'category_id' => [
                    'sometimes',
                    'integer',
                    'min:1',
                ],
                'is_available' => [
                    'sometimes',
                    'boolean',
                ],
                'per_page' => [
                    'sometimes',
                    'integer',
                    'between:1,100',
                ]],
        };
    }
}
