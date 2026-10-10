<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Http\Requests\Api\V1\ApiFormRequest;

/** Validate a customer's OTP and optional account details before verification. */
class VerifyCustomerOtpRequest extends ApiFormRequest
{
    /** OTP verification is public and completes authentication in the application service. */
    public function authorize(): bool
    {
        return true;
    }

    /** Return the exact fields used to verify OTP and create a customer account. */
    public function rules(): array
    {
        return [
            'mobile_number' => ['required', 'regex:/^[6-9][0-9]{9}$/'],
            'otp' => ['required', 'digits:6'],
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190'],
        ];
    }
}
