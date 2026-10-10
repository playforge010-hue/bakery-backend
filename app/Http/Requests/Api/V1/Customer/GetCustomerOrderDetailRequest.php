<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Http\Requests\Api\V1\ApiFormRequest;

/** Validate the order number query used to retrieve a customer's order detail. */
class GetCustomerOrderDetailRequest extends ApiFormRequest
{
    /** The order detail route is protected by customer Sanctum authentication. */
    public function authorize(): bool
    {
        return true;
    }

    /** Require the public order number expected by the existing endpoint. */
    public function rules(): array
    {
        return ['order_number' => ['required', 'string', 'max:40']];
    }
}
