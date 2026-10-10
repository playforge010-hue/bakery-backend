<?php

namespace App\Actions;

use App\Events\OrderChanged;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Broadcast order updates without allowing a websocket outage to fail a committed order change. */
class BroadcastOrderChange
{
    /** Publish the minimal order event and leave polling as the fallback during broker outages. */
    public function execute(Order $order, string $changeType): void
    {
        try {
            OrderChanged::dispatch($order, $changeType);
        } catch (Throwable $exception) {
            Log::warning('Order update could not be broadcast.', [
                'order_id' => $order->id,
                'change_type' => $changeType,
                'exception' => $exception::class,
            ]);
        }
    }
}
