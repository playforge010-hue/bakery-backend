<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Http\Requests\Api\V1\ApiFormRequest;

/** Validate adding or removing one product from the authenticated customer's favorites. */
class UpdateCustomerFavoriteRequest extends ApiFormRequest
{
    /** The favorites route is protected by customer Sanctum authentication. */
    public function authorize(): bool
    {
        return true;
    }

    /** Require a real product and an explicit desired favorite state. */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'is_favorite' => ['required', 'boolean'],
        ];
    }
}
