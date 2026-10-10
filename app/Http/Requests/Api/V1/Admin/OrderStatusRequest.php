<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Validation\Rule;

/** Validate the target state and optional staff note for an order transition. */
class OrderStatusRequest extends AdminFormRequest
{
    /** Route role middleware authorizes admin access before transition validation. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> Status is checked against the order transition graph in the action. */
    public function rules(): array
    {
        return ['id' => [
            'sometimes',
            'integer',
            'exists:orders,id',
        ],
            'order_number' => [
                'required_without:id',
                'nullable',
                'string',
                'exists:orders,order_number',
            ],
            'status' => [
                'required',
                Rule::in(\App\Enums\OrderStatus::staffTargets()),
            ],
            'note' => [
                'nullable',
                'string',
                'max:2000',
            ]];
    }
}
