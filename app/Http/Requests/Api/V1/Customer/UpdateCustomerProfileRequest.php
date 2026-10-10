<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Http\Requests\Api\V1\ApiFormRequest;

/** Validate editable customer profile fields and an optional avatar upload. */
class UpdateCustomerProfileRequest extends ApiFormRequest
{
    /** The authenticated customer may update only their own profile. */
    public function authorize(): bool
    {
        return true;
    }

    /** Limit profile writes to the fields exposed by the customer account UI. */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'email' => ['sometimes', 'nullable', 'email', 'max:190'],
            'preferred_language' => ['sometimes', 'in:gu,en,hi'],
            'avatar' => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}
