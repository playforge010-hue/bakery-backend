<?php

namespace App\Services\Admin;

use App\Enums\FulfillmentType;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Apply allowed operational order transitions atomically and retain an audit trail. */
class AdminOrderService
{
    private static function graph(): array
    {
        return [
            OrderStatus::Pending->value => [
                OrderStatus::Accepted->value,
                OrderStatus::Cancelled->value,
            ],
            OrderStatus::Confirmed->value => [
                OrderStatus::Accepted->value,
                OrderStatus::Preparing->value,
                OrderStatus::Cancelled->value,
            ],
            OrderStatus::Accepted->value => [
                OrderStatus::Preparing->value,
                OrderStatus::Cancelled->value,
            ],
            OrderStatus::Preparing->value => [
                OrderStatus::Ready->value,
                OrderStatus::Cancelled->value,
            ],
            OrderStatus::Ready->value => [
                OrderStatus::OutForDelivery->value,
                OrderStatus::PickedUp->value,
                OrderStatus::Cancelled->value,
            ],
            OrderStatus::OutForDelivery->value => [OrderStatus::Delivered->value],
        ];
    }

    /** Expose the same status graph used for writes, restricted to this fulfillment type. */
    public static function allowedStatuses(Order $order): array
    {
        $graph = self::graph();

        return array_values(array_filter(
            $graph[$order->order_status] ?? [],
            fn (string $status): bool => match ($status) {
                OrderStatus::OutForDelivery->value => $order->fulfillment_type === FulfillmentType::Delivery->value,
                OrderStatus::PickedUp->value => $order->fulfillment_type === FulfillmentType::Takeaway->value,
                default => true,
            }
        ));
    }

    /** Lock and validate an order transition, persist its history, and return the updated order. */
    public function transition(
        Order $order,
        string $target,
        int $actorId,
        ?string $note,
        ?array $requiredCurrentStatuses = null
    ): Order {
        return DB::transaction(function () use ($order, $target, $actorId, $note, $requiredCurrentStatuses): Order {
            $order = Order::query()
                ->lockForUpdate()
                ->findOrFail($order->id);

            if ($requiredCurrentStatuses !== null && ! in_array($order->order_status, $requiredCurrentStatuses, true)) {
                throw ValidationException::withMessages([
                    'status' => ['The order has changed. Refresh its details before trying again.'],
                ]);
            }

            if (! in_array($target, self::allowedStatuses($order), true)) {
                throw ValidationException::withMessages([
                    'status' => [
                        'This order cannot move from '.$order->order_status.' to '.$target.'.',
                    ],
                ]);
            }

            $timestampColumn = match ($target) {
                OrderStatus::Accepted->value => 'accepted_at',
                OrderStatus::Preparing->value => 'prepared_at',
                OrderStatus::Ready->value => 'ready_at',
                OrderStatus::OutForDelivery->value => 'out_for_delivery_at',
                OrderStatus::Delivered->value => 'delivered_at',
                OrderStatus::PickedUp->value => 'picked_up_at',
                OrderStatus::Cancelled->value => 'cancelled_at',
                default => null,
            };

            $previousStatus = $order->order_status;
            $order->order_status = $target;

            if ($timestampColumn !== null) {
                $order->{$timestampColumn} = now();
            }

            if ($target === OrderStatus::Cancelled->value && $note) {
                $order->cancellation_reason = $note;
            }

            $order->save();

            OrderStatusHistory::query()->create([
                'order_id' => $order->id,
                'old_status' => $previousStatus,
                'new_status' => $target,
                'changed_by_user_id' => $actorId,
                'note' => $note,
                'visible_to_customer' => true,
            ]);

            return $order->refresh();
        });
    }
}
