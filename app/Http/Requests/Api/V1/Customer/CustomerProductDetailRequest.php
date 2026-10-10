<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Http\Requests\Api\V1\ApiFormRequest;

/** Validate the slug identifying a customer-visible product. */
class CustomerProductDetailRequest extends ApiFormRequest
{
    /** Require a bounded URL-safe slug for product detail lookup. */
    public function rules(): array
    {
        return ['slug' => ['required', 'string', 'max:180', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/']];
    }
}
