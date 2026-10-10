<?php

namespace App\Services\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\SupportTicketStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\SupportTicket;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** Build date-scoped dashboard totals and compact chart series from bakery records. */
class AdminDashboardService
{
    /** Inject the shared date range resolver for dashboard queries. */
    public function __construct(
        private readonly AdminDateRangeService $dateRanges
    ) {
    }

    /** Return bakery dashboard totals and chart data for validated filters. */
    public function summarize(array $filters): array
    {
        $window = $this->dateRanges->resolve($filters);
        $orders = $this->ordersInWindow($window['from'], $window['to']);
        $paidOrders = (clone $orders)->where('payment_status', PaymentStatus::Paid->value);
        $registrationQuery = User::query()->where('role', 'customer');

        $this->applyWindow($registrationQuery, 'created_at', $window['from'], $window['to']);

        return [
            'range' => [
                'key' => $window['range'],
                'from' => $window['from']?->toDateString(),
                'to' => $window['to']?->toDateString(),
            ],
            'summary' => [
                'registered_customers' => $registrationQuery->count(),
                'orders' => (clone $orders)->count(),
                'revenue' => round((float) (clone $paidOrders)->sum('grand_total'), 2),
                'average_order_value' => round(
                    (float) (clone $orders)->avg('grand_total'),
                    2
                ),
                'open_orders' => (clone $orders)
                    ->whereIn('order_status', [
                        OrderStatus::Pending->value,
                        OrderStatus::Confirmed->value,
                        OrderStatus::Accepted->value,
                        OrderStatus::Preparing->value,
                        OrderStatus::Ready->value,
                        OrderStatus::OutForDelivery->value,
                    ])
                    ->count(),
                'active_products' => Product::query()->available()->count(),
                'open_support_tickets' => SupportTicket::query()
                    ->whereIn('status', [SupportTicketStatus::Open->value, SupportTicketStatus::InProgress->value])
                    ->count(),
            ],
            'status_breakdown' => (clone $orders)
                ->select('order_status', DB::raw('COUNT(*) AS total'))
                ->groupBy('order_status')
                ->orderBy('order_status')
                ->get()
                ->map(fn ($row) => [
                    'status' => $row->order_status,
                    'total' => (int) $row->total,
                ])
                ->values(),
            'series' => $this->chartSeries($window['from'], $window['to']),
            'recent_orders' => Order::query()
                ->when(
                    $window['from'],
                    fn (Builder $query) => $query->where(
                        'placed_at',
                        '>=',
                        $window['from']
                    )
                )
                ->when(
                    $window['to'],
                    fn (Builder $query) => $query->where(
                        'placed_at',
                        '<=',
                        $window['to']
                    )
                )
                ->latest('placed_at')
                ->latest('id')
                ->limit(8)
                ->get([
                    'id',
                    'order_number',
                    'customer_name',
                    'fulfillment_type',
                    'order_status',
                    'payment_status',
                    'grand_total',
                    'placed_at',
                ])->map(fn (Order $order) => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer_name' => $order->customer_name,
                    'fulfillment_type' => $order->fulfillment_type,
                    'order_status' => $order->order_status,
                    'payment_status' => $order->payment_status,
                    'grand_total' => (float) $order->grand_total,
                    'placed_at' => $order->placed_at?->toIso8601String(),
                ]),
        ];
    }

    /** Fill missing calendar buckets with zero activity so charts represent the whole selected window. */
    private function chartSeries(?CarbonImmutable $from, ?CarbonImmutable $to): array
    {
        $useMonthlyBuckets = ! $from
            || ! $to
            || $from->diffInDays($to) > 62;

        $driver = DB::connection()->getDriverName();
        $bucketExpression = $useMonthlyBuckets
            ? ($driver === 'sqlite' ? "strftime('%Y-%m', placed_at)" : "DATE_FORMAT(placed_at, '%Y-%m')")
            : 'DATE(placed_at)';

        $rows = Order::query()
            ->when(
                $from,
                fn (Builder $query) => $query->where('placed_at', '>=', $from)
            )
            ->when(
                $to,
                fn (Builder $query) => $query->where('placed_at', '<=', $to)
            )
            ->selectRaw($bucketExpression.' AS period')
            ->selectRaw('COUNT(*) AS orders')
            ->selectRaw(
                "SUM(CASE WHEN payment_status = 'paid' THEN grand_total ELSE 0 END) AS revenue"
            )
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(fn ($row) => [
                'period' => (string) $row->period,
                'orders' => (int) $row->orders,
                'revenue' => round((float) $row->revenue, 2),
            ])
            ->keyBy('period');

        if ($rows->isEmpty() && (! $from || ! $to)) {
            return [];
        }
        $first = $from ?? CarbonImmutable::parse($rows->keys()->first(), config('app.timezone'));
        $last = $to ?? CarbonImmutable::now(config('app.timezone'));
        $cursor = $useMonthlyBuckets ? $first->startOfMonth() : $first->startOfDay();
        $last = $useMonthlyBuckets ? $last->startOfMonth() : $last->startOfDay();
        $series = [];
        while ($cursor <= $last) {
            $period = $cursor->format($useMonthlyBuckets ? 'Y-m' : 'Y-m-d');
            $series[] = $rows->get($period, ['period' => $period, 'orders' => 0, 'revenue' => 0.0]);
            $cursor = $useMonthlyBuckets ? $cursor->addMonth() : $cursor->addDay();
        }

        return $series;
    }

    private function ordersInWindow(
        ?CarbonImmutable $from,
        ?CarbonImmutable $to
    ): Builder {
        return Order::query()
            ->when(
                $from,
                fn (Builder $query) => $query->where('placed_at', '>=', $from)
            )
            ->when(
                $to,
                fn (Builder $query) => $query->where('placed_at', '<=', $to)
            );
    }

    private function applyWindow(
        Builder $query,
        string $column,
        ?CarbonImmutable $from,
        ?CarbonImmutable $to
    ): void {
        if ($from) {
            $query->where($column, '>=', $from);
        }

        if ($to) {
            $query->where($column, '<=', $to);
        }
    }
}
