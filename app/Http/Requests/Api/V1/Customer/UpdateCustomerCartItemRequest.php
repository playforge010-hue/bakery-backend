<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Http\Requests\Api\V1\ApiFormRequest;

/** Validate a customer cart line quantity update. */
class UpdateCustomerCartItemRequest extends ApiFormRequest
{
    /** Require an item id and a bounded positive quantity. */
    public function rules(): array
    {
        return [
            'cart_item_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'integer', 'between:1,99'],
        ];
    }
}
