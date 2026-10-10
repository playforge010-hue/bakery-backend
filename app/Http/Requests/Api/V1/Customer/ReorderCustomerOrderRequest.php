<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Http\Requests\Api\V1\ApiFormRequest;

/** Validate the order number used to copy a previous order into the cart. */
class ReorderCustomerOrderRequest extends ApiFormRequest
{
    /** Require a bounded order number; ownership is checked by the operation. */
    public function rules(): array
    {
        return ['order_number' => ['required', 'string', 'max:50']];
    }
}
