<?php

namespace App\Http\Requests\Api\V1\Admin;

/** Validate bounded administration write payloads; endpoint-specific constraints remain explicit. */
class AdminMutationRequest extends AdminFormRequest
{
    /** Route role middleware authorizes admin access before mutation validation. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> Common safety limits for JSON-backed admin forms. */
    public function rules(): array
    {
        return [
            'id' => ['sometimes', 'integer', 'min:1'],
            'name' => ['sometimes', 'string', 'max:190'],
            'is_active' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'string', 'max:40'],
            'note' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'payload' => ['sometimes', 'array'],
            'data' => ['sometimes', 'array'],
            'image' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'banner_image' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'product_category_id' => ['sometimes', 'integer', 'exists:product_categories,id'],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:product_categories,id'],
            'slug' => ['sometimes', 'string', 'max:190'],
            'name_translations' => ['sometimes', 'array'],
            'description_translations' => ['sometimes', 'nullable', 'array'],
            'short_description_translations' => ['sometimes', 'nullable', 'array'],
            'base_price' => ['sometimes', 'numeric', 'min:0'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'product_type' => ['sometimes', 'string', 'max:60'],
            'food_type' => ['sometimes', 'string', 'max:60'],
            'sku' => ['sometimes', 'nullable', 'string', 'max:100'],
            'is_featured' => ['sometimes', 'boolean'],
            'is_bestseller' => ['sometimes', 'boolean'],
            'is_new' => ['sometimes', 'boolean'],
            'is_available' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'variants' => ['sometimes', 'array'],
            'variants.*.price' => ['required_with:variants', 'numeric', 'min:0'],
            'variants.*.name_translations' => ['required_with:variants', 'array'],
            'inventory' => ['sometimes', 'array'],
            'inventory.stock_quantity' => ['sometimes', 'integer', 'min:0'],
            'main_image' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'delivery_available' => ['sometimes', 'boolean'],
            'takeaway_available' => ['sometimes', 'boolean'],
            'online_payment_available' => ['sometimes', 'boolean'],
            'cod_available' => ['sometimes', 'boolean'],
            'minimum_order_amount' => ['sometimes', 'numeric', 'min:0'],
            'delivery_fee' => ['sometimes', 'numeric', 'min:0'],
            'code' => ['sometimes', 'string', 'max:60'],
            'discount_type' => ['sometimes', 'in:fixed,percentage'],
            'discount_value' => ['sometimes', 'numeric', 'gt:0'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
            'role' => ['sometimes', 'in:owner,admin,staff,delivery'],
            'mobile_number' => ['sometimes', 'string', 'max:30'],
            'email' => ['sometimes', 'nullable', 'email', 'max:190'],
            'password' => ['sometimes', 'string', 'min:12'],
        ];
    }
}
