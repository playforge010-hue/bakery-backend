<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Http\Requests\Api\V1\ApiFormRequest;

/** Validate the cart item identifier for a customer removal request. */
class RemoveCustomerCartItemRequest extends ApiFormRequest
{
    /** Require a positive cart item id; ownership is checked by the operation. */
    public function rules(): array
    {
        return ['cart_item_id' => ['required', 'integer', 'min:1']];
    }
}
