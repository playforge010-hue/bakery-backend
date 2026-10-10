<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Http\Requests\Api\V1\ApiFormRequest;

/** Validate the customer identity and optional registration details for OTP delivery. */
class SendCustomerOtpRequest extends ApiFormRequest
{
    /** Allow public OTP requests; throttling and account purpose checks run in the action. */
    public function authorize(): bool
    {
        return true;
    }

    /** Describe the accepted mobile number, purpose, and optional customer profile fields. */
    public function rules(): array
    {
        return [
            'mobile_number' => ['required', 'regex:/^[6-9][0-9]{9}$/'],
            'purpose' => ['sometimes', 'in:login,register'],
            'name' => ['required_if:purpose,register', 'nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190'],
        ];
    }
}
