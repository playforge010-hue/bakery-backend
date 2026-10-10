<?php

namespace App\Services\Admin;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;

/** Apply one operational window to both dispatcher tab counts and paginated cards. */
class AdminDispatcherService
{
    public const QUEUES = [
        'pending' => [OrderStatus::Pending->value, OrderStatus::Confirmed->value],
        'accepted' => [OrderStatus::Accepted->value],
        'preparing' => [OrderStatus::Preparing->value],
        'ready' => [OrderStatus::Ready->value],
        'out_for_delivery' => [OrderStatus::OutForDelivery->value],
        'completed' => [OrderStatus::Delivered->value, OrderStatus::PickedUp->value],
        'cancelled' => [OrderStatus::Cancelled->value, OrderStatus::Rejected->value],
    ];

    /** Keep all unfinished orders visible, together with orders placed during the last 24 hours. */
    public function orderQuery(): Builder
    {
        return Order::query()->where(function (Builder $query): void {
            $query->whereNotIn('order_status', [
                ...self::QUEUES['completed'],
                ...self::QUEUES['cancelled'],
            ])->orWhere('placed_at', '>=', now()->subDay());
        });
    }

    /** Count every matching order in MySQL, including cards outside the current page. */
    public function counts(Builder $query): array
    {
        $statusCounts = (clone $query)
            ->select('order_status')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('order_status')
            ->pluck('total', 'order_status');
        $counts = ['all' => (int) $statusCounts->sum()];

        foreach (self::QUEUES as $queue => $statuses) {
            $counts[$queue] = 0;

            foreach ($statuses as $status) {
                $counts[$queue] += (int) $statusCounts->get($status, 0);
            }
        }

        return $counts;
    }
}
