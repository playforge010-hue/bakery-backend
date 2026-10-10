<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Http\Requests\Api\V1\ApiFormRequest;

/** Validate the order number and optional reason for customer cancellation. */
class CancelCustomerOrderRequest extends ApiFormRequest
{
    /** Bound the customer-supplied cancellation details. */
    public function rules(): array
    {
        return [
            'order_number' => ['required', 'string', 'max:50'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
