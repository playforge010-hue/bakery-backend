<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Http\Requests\Api\V1\ApiFormRequest;

/** Validate optional pagination parameters for the customer's order history. */
class GetCustomerOrderListRequest extends ApiFormRequest
{
    /** The route is protected by customer Sanctum authentication. */
    public function authorize(): bool
    {
        return true;
    }

    /** Bound the page size to keep each order history response efficient. */
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'filter' => ['nullable', 'in:all,active,past'],
            'search' => ['nullable', 'string', 'max:120'],
        ];
    }
}
