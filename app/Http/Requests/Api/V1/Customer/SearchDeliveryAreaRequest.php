<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Http\Requests\Api\V1\ApiFormRequest;

/** Validate the optional search query used by delivery area autocomplete. */
class SearchDeliveryAreaRequest extends ApiFormRequest
{
    /** Bound the public catalog search string. */
    public function rules(): array
    {
        return ['search' => ['nullable', 'string', 'max:120']];
    }
}
