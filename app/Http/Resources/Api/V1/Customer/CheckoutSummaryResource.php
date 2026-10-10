<?php

namespace App\Http\Resources\Api\V1\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Expose only customer-visible line details and server-calculated checkout totals. */
class CheckoutSummaryResource extends JsonResource
{
    /** Shape trusted service output for the web and mobile clients. */
    public function toArray(Request $request): array
    {
        return [
            'items' => $this['items'],
            'totals' => $this['totals'],
            'fulfillment_type' => $this['fulfillment_type'] ?? null,
            'delivery' => $this['delivery'] ?? null,
            'minimum_order_met' => $this['minimum_order_met'] ?? true,
        ];
    }
}
