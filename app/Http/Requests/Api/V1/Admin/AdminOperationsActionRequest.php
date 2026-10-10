<?php

namespace App\Http\Requests\Api\V1\Admin;

/** Validate coupon, support, customer and activity-log operations for administration. */
class AdminOperationsActionRequest extends AdminFormRequest
{
    /** Route role middleware authorizes admin access before operations validation. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> Route-specific write or listing constraints. */
    public function rules(): array
    {
        return match (basename($this->path())) {
            'create-coupon',
            'update-coupon' => ['id' => [
                'sometimes',
                'integer',
                'exists:coupons,id',
            ],
                'code' => [
                    'required',
                    'string',
                    'max:60',
                ],
                'title_translations' => [
                    'required',
                    'array',
                ],
                'description_translations' => [
                    'nullable',
                    'array',
                ],
                'discount_type' => [
                    'required',
                    'in:fixed,percentage',
                ],
                'discount_value' => [
                    'required',
                    'numeric',
                    'gt:0',
                ],
                'max_discount_amount' => [
                    'nullable',
                    'numeric',
                    'min:0',
                ],
                'minimum_order_amount' => [
                    'nullable',
                    'numeric',
                    'min:0',
                ],
                'starts_at' => [
                    'nullable',
                    'date',
                ],
                'ends_at' => [
                    'nullable',
                    'date',
                    'after_or_equal:starts_at',
                ],
                'usage_limit' => [
                    'nullable',
                    'integer',
                    'min:1',
                ],
                'usage_limit_per_customer' => [
                    'nullable',
                    'integer',
                    'min:1',
                ],
                'applies_to_fulfillment_type' => [
                    'nullable',
                    'in:delivery,takeaway',
                ],
                'is_active' => [
                    'sometimes',
                    'boolean',
                ]],
            'update-coupon-status' => ['id' => [
                'required',
                'integer',
                'exists:coupons,id',
            ],
                'is_active' => [
                    'required',
                    'boolean',
                ]],
            'delete-coupon' => ['id' => [
                'required',
                'integer',
                'exists:coupons,id',
            ]],
            'get-ticket-detail','reply-ticket',
            'update-ticket-status' => ['id' => [
                'required_without:ticket_number',
                'integer',
                'exists:support_tickets,id',
            ],
                'ticket_number' => [
                    'required_without:id',
                    'string',
                    'exists:support_tickets,ticket_number',
                ],
                'message' => [
                    basename($this->path()) === 'reply-ticket' ? 'required' : 'sometimes',
                    'string',
                    'max:10000',
                ],
                'status' => [
                    basename($this->path()) === 'update-ticket-status' ? 'required' : 'sometimes',
                    'in:open,in_progress,resolved,closed',
                ],
                'priority' => [
                    'sometimes',
                    'in:low,normal,high,urgent',
                ],
                'assigned_to_user_id' => [
                    'sometimes',
                    'nullable',
                    'integer',
                    'exists:users,id',
                ]],
            'update-customer-status' => ['customer_id' => ['required', 'integer', 'exists:users,id'],
                'is_active' => ['required', 'boolean']],
            'update-staff-status' => ['staff_id' => ['required', 'integer', 'exists:users,id'],
                'is_active' => ['required', 'boolean']],
            default => ['search' => [
                'sometimes',
                'string',
                'max:190',
            ],
                'status' => [
                    'sometimes',
                    'string',
                    'max:40',
                ],
                'per_page' => [
                    'sometimes',
                    'integer',
                    'between:1,100',
                ]],
        };
    }
}
