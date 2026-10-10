<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Http\Requests\Api\V1\ApiFormRequest;
use App\Enums\FulfillmentType;
use Illuminate\Validation\Rule;

/** Validate fulfillment type for the customer's open pickup and delivery slots. */
class AvailableSchedulesRequest extends ApiFormRequest
{
    /** The available schedule list is public catalog information. */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** Bound the schedule query to a supported fulfillment type. */
    public function rules(): array
    {
        return ['fulfillment_type' => ['required', Rule::enum(FulfillmentType::class)], 'days' => ['sometimes', 'integer', 'min:1', 'max:14']];
    }
}
