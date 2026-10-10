<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Http\Requests\Api\V1\ApiFormRequest;

/** Validate address edits and the identifier of the customer's saved address. */
class UpdateCustomerAddressRequest extends ApiFormRequest
{
    /** Accept partial address changes while requiring the owned address id. */
    public function rules(): array
    {
        return [
            'address_id' => ['required', 'integer', 'min:1'],
            'label' => ['sometimes', 'nullable', 'string', 'max:50'],
            'receiver_name' => ['sometimes', 'required', 'string', 'max:120'],
            'receiver_mobile_number' => ['sometimes', 'required', 'string', 'max:20'],
            'address_line_1' => ['sometimes', 'required_without:address', 'string', 'max:255'],
            'address' => ['sometimes', 'required_without:address_line_1', 'string', 'max:255'],
            'address_line_2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'landmark' => ['sometimes', 'nullable', 'string', 'max:255'],
            'area' => ['sometimes', 'required', 'string', 'max:120'],
            'city' => ['sometimes', 'required', 'string', 'max:120'],
            'state' => ['sometimes', 'required', 'string', 'max:120'],
            'pincode' => ['sometimes', 'required', 'string', 'max:20'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
