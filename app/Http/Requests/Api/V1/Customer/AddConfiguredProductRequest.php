<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Http\Requests\Api\V1\ApiFormRequest;
use App\Enums\FulfillmentType;
use Illuminate\Validation\Rule;

/** Validate customer product options while leaving all pricing authoritative to catalog data. */
class AddConfiguredProductRequest extends ApiFormRequest
{
    /** Signed-in customers may submit a configured product and optional customization image. */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** Validate product selections, quantity and allowed upload formats. */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'product_variant_id' => ['nullable', 'integer'],
            'flavour_id' => ['nullable', 'integer'],
            'egg_option' => ['nullable', 'string', 'max:30'],
            'addons' => ['sometimes', 'array'],
            'addons.*.id' => ['required', 'integer', 'distinct'],
            'addons.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'quantity' => ['required', 'integer', 'min:1', 'max:50'],
            'cake_message' => ['nullable', 'string', 'max:200'],
            'fulfillment_type' => ['nullable', Rule::enum(FulfillmentType::class)],
            'custom_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}
