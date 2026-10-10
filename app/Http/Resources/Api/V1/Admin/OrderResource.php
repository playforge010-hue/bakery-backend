<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Services\Admin\AdminOrderService;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Expose only operational order fields required by the administration UI. */
class OrderResource extends JsonResource
{
    /** Return the card, totals, customization, and history fields used by order operations. */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'order_status' => $this->order_status,
            'allowed_statuses' => AdminOrderService::allowedStatuses($this->resource),
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_mobile_number,
            'customer' => [
                'name' => $this->customer_name,
                'mobile' => $this->customer_mobile_number,
            ],
            'fulfillment_type' => $this->fulfillment_type,
            'status' => $this->order_status,
            'payment' => [
                'status' => $this->payment_status,
                'method' => $this->payment_method,
            ],
            'scheduled_for' => $this->scheduled_for?->toIso8601String(),
            'placed_at' => $this->placed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'total' => (float) $this->grand_total,
            'totals' => [
                'subtotal' => (float) $this->subtotal,
                'addons' => (float) $this->addon_total,
                'discount' => (float) $this->discount_total,
                'delivery_fee' => (float) $this->delivery_fee,
                'tax' => (float) $this->tax_total,
                'total' => (float) $this->grand_total,
            ],
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn (OrderItem $item) => [
                'name' => $item->product_name,
                'variant' => $item->variant_name,
                'quantity' => (int) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'line_total' => (float) $item->line_total,
                'image_url' => $item->relationLoaded('product') ? $item->product?->image_url : null,
                'flavour' => $item->selected_flavour_name,
                'egg_option' => $item->egg_option,
                'cake_message' => $item->cake_message,
                'addons' => $item->relationLoaded('addons') ? $item->addons->map(fn ($addon) => [
                    'name' => $addon->addon_name,
                    'quantity' => (int) $addon->quantity,
                    'total' => (float) $addon->line_total,
                ])->values() : [],
                'uploads' => $item->relationLoaded('uploads') ? $item->uploads->map(fn ($upload) => [
                    'name' => $upload->original_name,
                    'image_url' => $upload->image_url,
                ])->values() : [],
            ])),
            'delivery_address' => $this->formatAddress($this->delivery_address_json),
            'delivery_address_details' => $this->delivery_address_json,
            'pickup_address' => $this->formatAddress($this->pickup_address_json),
            'customer_note' => $this->customer_note,
            'internal_note' => $this->internal_note,
            'cancellation_reason' => $this->cancellation_reason,
            'status_history' => $this->whenLoaded('histories', fn () => $this->histories->sortBy('id')->values()->map(fn (OrderStatusHistory $history) => [
                'status' => $history->new_status,
                'note' => $history->note,
                'at' => $history->created_at?->toIso8601String(),
            ])),
        ];
    }

    /** Flatten a saved delivery/pickup snapshot into the one-line address used by the order UI. */
    private function formatAddress(?array $address): ?string
    {
        if (! $address) {
            return null;
        }

        return implode(', ', array_values(array_filter([
            $address['address_line_1'] ?? null,
            $address['address_line_2'] ?? null,
            $address['landmark'] ?? null,
            $address['area'] ?? null,
            $address['city'] ?? null,
            $address['pincode'] ?? null,
        ], fn ($part) => is_string($part) && trim($part) !== '')));
    }
}
