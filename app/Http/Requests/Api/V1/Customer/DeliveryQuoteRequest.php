<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Http\Requests\Api\V1\ApiFormRequest;

/** Validate the customer pin used to request distance delivery pricing. */
class DeliveryQuoteRequest extends ApiFormRequest
{
    /** Delivery eligibility is publicly readable for a selected map location. */
    public function authorize(): bool
    {
        return true;
    }

    /** Require a complete geographic point; client fees and distances are ignored. */
    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ];
    }
}
