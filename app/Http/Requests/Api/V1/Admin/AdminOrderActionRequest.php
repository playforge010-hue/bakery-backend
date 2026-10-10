<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\FulfillmentType;
use App\Enums\OrderStatus;
use Illuminate\Validation\Rule;

/** Validate order search, detail and action identifiers received from the staff portal. */
class AdminOrderActionRequest extends AdminFormRequest
{
    /** Route role middleware authorizes admin access before order validation. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> Query constraints for list, detail, or a status transition. */
    public function rules(): array
    {
        return match (basename($this->path())) {
            'get-order-detail' => ['id' => ['required', 'integer', 'exists:orders,id']],
            'update-order-status' => ['id' => [
                'required',
                'integer',
                'exists:orders,id',
            ],
                'status' => [
                    'required',
                    Rule::in(OrderStatus::staffTargets()),
                ],
                'note' => [
                    'nullable',
                    'string',
                    'max:2000',
                ]],
            'accept-order',
            'mark-order-as-preparing',
            'mark-order-as-ready',
            'mark-order-as-out-for-delivery',
            'mark-order-as-delivered',
            'mark-order-as-picked-up',
            'cancel-order' => ['id' => [
                'required',
                'integer',
                'exists:orders,id',
            ],
                'note' => [
                    'nullable',
                    'string',
                    'max:2000',
                ],
                'reason' => [
                    'sometimes',
                    'nullable',
                    'string',
                    'max:2000',
                ]],
            'update-order-internal-note' => ['order_number' => [
                'required',
                'string',
                'exists:orders,order_number',
            ],
                'internal_note' => [
                    'nullable',
                    'string',
                    'max:5000',
                ]],
            default => ['search' => [
                'sometimes',
                'string',
                'max:190',
            ],
                'customer' => [
                    'sometimes',
                    'string',
                    'max:190',
                ],
                'queue' => [
                    'sometimes',
                    'in:active,active_or_recent,completed',
                ],
                'status' => [
                    'sometimes',
                    'string',
                    'max:40',
                ],
                'range' => [
                    'sometimes',
                    'in:today,yesterday,this_week,last_week,this_month,last_month,this_year,last_year,all,custom,last_24_hours',
                ],
                'from_date' => [
                    'required_if:range,custom',
                    'nullable',
                    'date_format:Y-m-d',
                ],
                'to_date' => [
                    'required_if:range,custom',
                    'nullable',
                    'date_format:Y-m-d',
                    'after_or_equal:from_date',
                ],
                'fulfillment_type' => [
                    'sometimes',
                    Rule::enum(FulfillmentType::class),
                ],
                'sort_by' => [
                    'sometimes',
                    'in:placed_at,grand_total,order_status,customer_name,order_number',
                ],
                'sort_direction' => [
                    'sometimes',
                    'in:asc,desc',
                ],
                'format' => ['sometimes', 'in:csv,pdf'],
                'page' => ['sometimes', 'integer', 'min:1'],
                'per_page' => [
                    'sometimes',
                    'integer',
                    'between:1,100',
                ]],
        };
    }
}
