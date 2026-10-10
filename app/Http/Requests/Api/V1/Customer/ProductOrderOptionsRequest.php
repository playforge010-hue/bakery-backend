<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Http\Requests\Api\V1\ApiFormRequest;

/** Validate the slug used to load customer-visible product choices for ordering. */
class ProductOrderOptionsRequest extends ApiFormRequest
{
    /** Product configurations are public catalog data. */
    public function authorize(): bool
    {
        return true;
    }

    /** Restrict the lookup key to a valid catalog slug. */
    public function rules(): array
    {
        return ['slug' => ['required', 'string', 'max:190']];
    }
}
