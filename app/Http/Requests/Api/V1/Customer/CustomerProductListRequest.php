<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Http\Requests\Api\V1\ApiFormRequest;

/** Validate customer catalog search and category filters. */
class CustomerProductListRequest extends ApiFormRequest
{
    /** Bound filters used to query customer-visible products. */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:120'],
        ];
    }
}
