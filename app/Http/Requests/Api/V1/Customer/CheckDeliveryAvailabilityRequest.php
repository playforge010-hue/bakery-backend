<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Http\Requests\Api\V1\ApiFormRequest;

/** Validate the selected map coordinates used for distance-based delivery. */
class CheckDeliveryAvailabilityRequest extends ApiFormRequest
{
    /** Require a valid coordinate pair for an authoritative delivery quote. */
    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ];
    }
}
