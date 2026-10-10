<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Http\Requests\Api\V1\ApiFormRequest;

/** Validate a coupon code submitted for the authenticated customer's cart. */
class ApplyCustomerCouponRequest extends ApiFormRequest
{
    /** Require a bounded coupon code instead of silently selecting a default. */
    public function rules(): array
    {
        return ['coupon_code' => ['required', 'string', 'max:50']];
    }
}
