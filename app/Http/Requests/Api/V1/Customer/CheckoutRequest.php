<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Http\Requests\Api\V1\ApiFormRequest;
use App\Enums\FulfillmentType;
use App\Enums\PaymentMethod;
use Illuminate\Validation\Rule;

/** Validate fulfillment, address coordinates and requested schedule before order placement. */
class CheckoutRequest extends ApiFormRequest
{
    /** Only authenticated customers can price or submit their own cart. */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** Keep coordinates paired and constrain geographic values to valid world ranges. */
    public function rules(): array
    {
        return [
            'fulfillment_type' => ['required', Rule::enum(FulfillmentType::class)],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'customer_address_id' => ['nullable', 'integer'],
            'delivery_address_label' => ['required_if:fulfillment_type,delivery', 'nullable', 'string', 'max:80'],
            'delivery_address_text' => ['required_if:fulfillment_type,delivery', 'nullable', 'string', 'max:500'],
            'delivery_latitude' => ['required_if:fulfillment_type,delivery', 'nullable', 'numeric', 'between:-90,90', 'required_with:delivery_longitude'],
            'delivery_longitude' => ['required_if:fulfillment_type,delivery', 'nullable', 'numeric', 'between:-180,180', 'required_with:delivery_latitude'],
            'scheduled_for' => ['required', 'date', 'after:now'],
            'customer_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
