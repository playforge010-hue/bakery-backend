<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Http\Requests\Api\V1\ApiFormRequest;

/** Validate the address identifier for a customer address removal request. */
class DeleteCustomerAddressRequest extends ApiFormRequest
{
    /** Require a positive address id; ownership is checked by the operation. */
    public function rules(): array
    {
        return ['address_id' => ['required', 'integer', 'min:1']];
    }
}
