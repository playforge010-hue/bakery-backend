<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Notify the bakery team and the owning customer about new orders and status changes. */
class OrderChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        private readonly Order $order,
        public readonly string $changeType
    ) {
    }

    /** Send only operational fields required to refresh order cards and tracking. */
    public function broadcastWith(): array
    {
        return [
            'change_type' => $this->changeType,
            'order' => [
                'id' => (int) $this->order->id,
                'order_number' => $this->order->order_number,
                'status' => $this->order->order_status,
                'fulfillment_type' => $this->order->fulfillment_type,
                'customer_name' => $this->order->customer_name,
                'total' => (float) $this->order->grand_total,
                'updated_at' => $this->order->updated_at?->toIso8601String(),
            ],
        ];
    }

    /** Address events to the authorized bakery dispatcher and this order's customer. */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('admin.orders'),
            new PrivateChannel('orders.'.$this->order->order_number),
        ];
    }

    /** Keep the event name stable and explicit for Laravel Echo listeners. */
    public function broadcastAs(): string
    {
        return 'order.changed';
    }
}
