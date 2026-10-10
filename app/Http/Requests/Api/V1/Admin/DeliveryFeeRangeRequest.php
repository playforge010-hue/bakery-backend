<?php

namespace App\Http\Requests\Api\V1\Admin;

/** Validate explicit delivery range creation, update, status and deletion payloads. */
class DeliveryFeeRangeRequest extends AdminFormRequest
{
    /** Admin role middleware controls access to delivery configuration. */
    public function authorize(): bool
    {
        return true;
    }

    /** Require identifiers for existing ranges and bound numeric values to schema precision. */
    public function rules(): array
    {
        $action = basename($this->path());
        $identity = ['id' => ['required', 'integer', 'exists:delivery_fee_ranges,id']];
        if ($action === 'delete-fee-range') {
            return $identity;
        }
        if ($action === 'update-fee-range-status') {
            return $identity + ['is_active' => ['required', 'boolean']];
        }

        return ($action === 'update-fee-range' ? $identity : ['id' => ['prohibited']]) + [
            'minimum_distance_km' => ['required', 'numeric', 'decimal:0,2', 'between:0,99999.99'],
            'maximum_distance_km' => ['required', 'numeric', 'decimal:0,2', 'gt:minimum_distance_km', 'max:99999.99'],
            'delivery_fee' => ['required', 'numeric', 'decimal:0,2', 'between:0,99999999.99'],
            'estimated_delivery_minutes' => ['required', 'integer', 'between:1,65535'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'between:0,2147483647'],
        ];
    }
}
