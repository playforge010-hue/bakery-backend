<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Http\Requests\Api\V1\ApiFormRequest;
use App\Enums\FulfillmentType;
use Illuminate\Validation\Rule;

/** Validate fulfillment and optional destination used for live checkout pricing. */
class CheckoutAvailabilityRequest extends ApiFormRequest
{
    /** Requires an authenticated customer cart. */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** Require paired, valid coordinates for delivery estimates. */
    public function rules(): array
    {
        return ['fulfillment_type' => [
            'required',
            Rule::enum(FulfillmentType::class),
        ], 'customer_address_id' => [
            'nullable',
            'integer',
        ], 'delivery_latitude' => [
            'required_if:fulfillment_type,delivery',
            'nullable',
            'numeric',
            'between:-90,90',
            'required_with:delivery_longitude',
        ], 'delivery_longitude' => [
            'required_if:fulfillment_type,delivery',
            'nullable',
            'numeric',
            'between:-180,180',
            'required_with:delivery_latitude',
        ]];
    }
}
