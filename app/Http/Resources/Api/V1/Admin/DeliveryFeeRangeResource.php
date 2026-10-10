<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Expose only editable distance pricing fields. */
class DeliveryFeeRangeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'minimum_distance_km' => (float) $this->minimum_distance_km,
            'maximum_distance_km' => (float) $this->maximum_distance_km,
            'delivery_fee' => (float) $this->delivery_fee,
            'estimated_delivery_minutes' => (int) $this->estimated_delivery_minutes,
            'is_active' => (bool) $this->is_active,
            'sort_order' => (int) $this->sort_order,
        ];
    }
}
