<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Http\Requests\Api\V1\ApiFormRequest;

/** Validate a new customer-owned delivery address. */
class CreateCustomerAddressRequest extends ApiFormRequest
{
    /** Require the address and recipient details used to deliver an order. */
    public function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:50'],
            'receiver_name' => ['required', 'string', 'max:120'],
            'receiver_mobile_number' => ['required', 'string', 'max:20'],
            'address_line_1' => ['required_without:address', 'string', 'max:255'],
            'address' => ['required_without:address_line_1', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'area' => ['required', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'pincode' => ['required', 'string', 'max:20'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
