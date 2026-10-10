<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Validation\Rule;

/** Validate product attributes, nested sellable options, inventory and gallery uploads. */
class AdminProductRequest extends AdminFormRequest
{
    use \App\Traits\NormalizesCatalogPayload;
    /** Route role middleware authorizes admin access before product validation. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> Only product fields and supported child records are accepted. */
    public function rules(): array
    {
        $isUpdate = basename($this->path()) === 'update-product';

        return [
            'id' => [
                $isUpdate ? 'required' : 'sometimes',
                'integer',
                'exists:products,id',
            ],
            'product_category_id' => ['required', 'integer', 'exists:product_categories,id'],
            'name_translations' => ['required', 'array'],
            'short_description_translations' => ['nullable', 'array'],
            'description_translations' => ['nullable', 'array'],
            'slug' => [
                'sometimes',
                'string',
                'max:190',
                Rule::unique('products', 'slug')->ignore($this->input('id')),
            ],
            'sku' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('products', 'sku')->ignore($this->input('id')),
            ],
            'base_price' => ['required', 'numeric', 'min:0'],
            'price_starts_from' => ['sometimes', 'boolean'],
            'product_type' => ['required', 'string', 'max:60'],
            'food_type' => ['required', 'string', 'max:60'],
            'is_eggless_available' => ['sometimes', 'boolean'],
            'is_egg_available' => ['sometimes', 'boolean'],
            'is_customizable' => ['sometimes', 'boolean'],
            'allow_message' => ['sometimes', 'boolean'],
            'allow_photo_upload' => ['sometimes', 'boolean'],
            'min_preparation_minutes' => ['sometimes', 'integer', 'min:0'],
            'max_preparation_minutes' => ['nullable', 'integer', 'min:0', 'gte:min_preparation_minutes'],
            'is_featured' => ['sometimes', 'boolean'],
            'is_bestseller' => ['sometimes', 'boolean'],
            'is_new' => ['sometimes', 'boolean'],
            'is_available' => ['sometimes', 'boolean'],
            'sold_out_until' => ['nullable', 'date'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'seo_title' => ['nullable', 'string', 'max:190'],
            'seo_description' => ['nullable', 'string', 'max:1000'],
            'main_image' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'variants' => ['sometimes', 'array'],
            'variants.*.id' => ['sometimes', 'integer', 'distinct', Rule::exists('product_variants', 'id')->where('product_id', $this->input('id'))],
            'variants.*.name_translations' => ['required', 'array'],
            'variants.*.sku' => ['nullable', 'string', 'max:100'],
            'variants.*.price' => ['required', 'numeric', 'min:0'],
            'variants.*.compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.weight_value' => ['nullable', 'numeric', 'min:0'],
            'variants.*.weight_unit' => ['nullable', 'string', 'max:30'],
            'variants.*.is_default' => ['sometimes', 'boolean'],
            'variants.*.is_active' => ['sometimes', 'boolean'],
            'variants.*.sort_order' => ['sometimes', 'integer', 'min:0'],
            'flavour_ids' => ['sometimes', 'array'],
            'flavour_ids.*' => ['integer', 'distinct', 'exists:product_flavours,id'],
            'addons' => ['sometimes', 'array'],
            'addons.*.id' => ['required_with:addons', 'integer', 'distinct', 'exists:product_addons,id'],
            'addons.*.is_required' => ['sometimes', 'boolean'],
            'addons.*.max_quantity' => ['nullable', 'integer', 'min:1'],
            'inventory' => ['sometimes', 'array'],
            'inventory.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'inventory.stock_quantity' => ['nullable', 'integer', 'min:0'],
            'inventory.low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'inventory.track_stock' => ['sometimes', 'boolean'],
            'inventory.is_sold_out' => ['sometimes', 'boolean'],
            'inventory.sold_out_until' => ['nullable', 'date'],
            'customization' => ['sometimes', 'array'],
            'customization.allow_message' => ['sometimes', 'boolean'],
            'customization.message_label_translations' => ['nullable', 'array'],
            'customization.message_max_length' => ['nullable', 'integer', 'min:1'],
            'customization.allow_photo_upload' => ['sometimes', 'boolean'],
            'customization.photo_upload_label_translations' => ['nullable', 'array'],
            'customization.allow_flavour_selection' => ['sometimes', 'boolean'],
            'customization.allow_egg_selection' => ['sometimes', 'boolean'],
            'customization.allow_delivery_date_selection' => ['sometimes', 'boolean'],
            'customization.min_notice_minutes' => ['nullable', 'integer', 'min:0'],
            'customization.max_schedule_days' => ['nullable', 'integer', 'min:1'],
            ...$this->translationRules('name_translations', true),
            ...$this->translationRules('short_description_translations', false, 10000),
            ...$this->translationRules('description_translations', false, 10000),
            ...$this->translationRules('variants.*.name_translations', true),
            ...$this->translationRules('customization.message_label_translations'),
            ...$this->translationRules('customization.photo_upload_label_translations'),
        ];
    }
}
