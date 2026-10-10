<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Http\Requests\Api\V1\ApiFormRequest;
use App\Enums\FulfillmentType;
use Illuminate\Validation\Rule;

/** Validate the legacy customer cart add payload; prices always come from the catalog. */
class AddCustomerCartItemRequest extends ApiFormRequest
{
    /** The cart route is protected by customer Sanctum authentication. */
    public function authorize(): bool
    {
        return true;
    }

    /** Return product customization inputs accepted by the existing cart contract. */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'product_variant_id' => ['nullable', 'integer'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'fulfillment_type' => ['sometimes', Rule::enum(FulfillmentType::class)],
            'egg_option' => ['nullable', 'string', 'max:30'],
            'cake_message' => ['nullable', 'string', 'max:120'],
            'variant_name' => ['nullable', 'string', 'max:120'],
            'flavour_name' => ['nullable', 'string', 'max:120'],
        ];
    }
}
