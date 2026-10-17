<?php

namespace App\Actions\Admin;

use App\Models\OrderStatusHistory;

/** Read a compact, ordered feed so open admin pages can alert without WebSockets. */
class GetOrderChanges
{
    private const PAGE_SIZE = 100;

    public function execute(?int $afterId): array
    {
        if ($afterId === null) {
            return [
                'events' => [],
                'cursor' => (int) (OrderStatusHistory::query()->max('id') ?? 0),
                'has_more' => false,
            ];
        }

        $histories = OrderStatusHistory::query()
            ->where('id', '>', $afterId)
            ->whereHas('order')
            ->with('order:id,order_number,order_status,customer_name,updated_at')
            ->orderBy('id')
            ->limit(self::PAGE_SIZE + 1)
            ->get();

        $hasMore = $histories->count() > self::PAGE_SIZE;
        $page = $histories->take(self::PAGE_SIZE);

        return [
            'events' => $page->map(fn (OrderStatusHistory $history): array => [
                'event_id' => (int) $history->id,
                'change_type' => $history->old_status === null ? 'created' : 'status_updated',
                'order' => [
                    'id' => (int) $history->order->id,
                    'order_number' => $history->order->order_number,
                    'status' => $history->new_status,
                    'customer_name' => $history->order->customer_name,
                    'updated_at' => $history->created_at?->toIso8601String(),
                ],
            ])->values()->all(),
            'cursor' => (int) ($page->last()?->id ?? $afterId),
            'has_more' => $hasMore,
        ];
    }
}
