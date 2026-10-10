<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\OrderStatus;
use App\Actions\Admin\WriteAdminAuditLog;
use App\Actions\BroadcastOrderChange;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\AdminOrderActionRequest;
use App\Http\Requests\Api\V1\Admin\DispatcherOrdersRequest;
use App\Http\Requests\Api\V1\Admin\OrderStatusRequest;
use App\Http\Resources\Api\V1\Admin\OrderResource;
use App\Models\Order;
use App\Services\Admin\AdminOrderService;
use App\Services\Admin\AdminDateRangeService;
use App\Services\Admin\AdminDispatcherService;
use App\Traits\ApiResponseTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Provide filtered order operations for the kitchen and delivery/takeaway staff. */
class AdminOrderController extends Controller
{
    use ApiResponseTrait;

    /** Inject the order change broadcast used after a successful state transition. */
    public function __construct(
        private readonly BroadcastOrderChange $broadcastOrderChange
    ) {
    }

    /** Load a dispatcher queue and counts using one shared operational window. */
    public function getDispatcherOrders(
        DispatcherOrdersRequest $request,
        AdminDispatcherService $dispatcher
    ): JsonResponse {
        $filters = $request->validated();
        $queue = $filters['queue'] ?? 'all';
        $query = $dispatcher->orderQuery();
        $counts = $dispatcher->counts($query);

        if ($queue !== 'all') {
            $query->whereIn('order_status', AdminDispatcherService::QUEUES[$queue]);
        }

        $orders = $query->with(['items.product:id,main_image_path', 'items.addons'])
            ->orderByDesc('placed_at')
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 25);

        return $this->successResponse('Dispatcher orders loaded.', OrderResource::collection($orders), [
            'counts' => $counts,
            'current_page' => $orders->currentPage(),
            'last_page' => $orders->lastPage(),
            'total' => $orders->total(),
            'per_page' => $orders->perPage(),
        ]);
    }

    /** Filter and paginate orders by status, fulfillment and customer/order search text. */
    public function getOrderList(
        AdminOrderActionRequest $request,
        AdminDateRangeService $dateRanges
    ): JsonResponse
    {
        $query = $this->filteredOrderQuery($request, $dateRanges);
        $rows = $query->paginate($request->integer('per_page', 25));

        return $this->successResponse('Orders loaded.', OrderResource::collection($rows), [
            'current_page' => $rows->currentPage(),
            'last_page' => $rows->lastPage(),
            'total' => $rows->total(),
            'per_page' => $rows->perPage(),
        ]);
    }

    /** Download CSV or PDF through the authenticated route using the current list filters. */
    public function exportOrderList(
        AdminOrderActionRequest $request,
        AdminDateRangeService $dateRanges,
        \App\Services\Admin\AdminOrderExportService $exports
    ): StreamedResponse|\Symfony\Component\HttpFoundation\Response {
        return $exports->download(
            $this->filteredOrderQuery($request, $dateRanges),
            $request->validated()
        );
    }

    /** Load a single order using the numeric identifier supplied by the portal. */
    public function getOrderDetail(AdminOrderActionRequest $request): JsonResponse
    {
        $order = Order::query()->with([
            'items.addons',
            'items.product:id,main_image_path',
            'items.uploads',
            'histories',
        ])->findOrFail($request->validated()['id']);

        return $this->successResponse(
            'Order details loaded.',
            new OrderResource($order)
        );
    }

    /** Apply a status selected by an authorized manager after graph and fulfillment checks. */
    public function updateOrderStatus(
        OrderStatusRequest $request,
        AdminOrderService $service,
        WriteAdminAuditLog $audit
    ): JsonResponse {
        $data = $request->validated();

        $order = isset($data['id'])
            ? Order::query()->findOrFail($data['id'])
            : Order::query()
                ->where('order_number', $data['order_number'])
                ->firstOrFail();

        return $this->completeTransition($request, $order, $data['status'], $data['note'] ?? null, $service, $audit);
    }

    /** Accept a pending customer order. */
    public function acceptOrder(
        AdminOrderActionRequest $request,
        AdminOrderService $service,
        WriteAdminAuditLog $audit
    ): JsonResponse {
        return $this->applyFixedTransition($request, OrderStatus::Accepted->value, $service, $audit);
    }

    /** Start preparation after order confirmation/acceptance. */
    public function markOrderAsPreparing(
        AdminOrderActionRequest $request,
        AdminOrderService $service,
        WriteAdminAuditLog $audit
    ): JsonResponse {
        return $this->applyFixedTransition($request, OrderStatus::Preparing->value, $service, $audit);
    }

    /** Mark prepared goods ready for dispatch or counter collection. */
    public function markOrderAsReady(
        AdminOrderActionRequest $request,
        AdminOrderService $service,
        WriteAdminAuditLog $audit
    ): JsonResponse {
        return $this->applyFixedTransition($request, OrderStatus::Ready->value, $service, $audit);
    }

    /** Dispatch a delivery order. */
    public function markOrderAsOutForDelivery(
        AdminOrderActionRequest $request,
        AdminOrderService $service,
        WriteAdminAuditLog $audit
    ): JsonResponse {
        return $this->applyFixedTransition($request, OrderStatus::OutForDelivery->value, $service, $audit);
    }

    /** Complete a delivery order. */
    public function markOrderAsDelivered(
        AdminOrderActionRequest $request,
        AdminOrderService $service,
        WriteAdminAuditLog $audit
    ): JsonResponse {
        return $this->applyFixedTransition($request, OrderStatus::Delivered->value, $service, $audit);
    }

    /** Complete a takeaway collection. */
    public function markOrderAsPickedUp(
        AdminOrderActionRequest $request,
        AdminOrderService $service,
        WriteAdminAuditLog $audit
    ): JsonResponse {
        return $this->applyFixedTransition($request, OrderStatus::PickedUp->value, $service, $audit);
    }

    /** Cancel an order with an optional reason retained in its status history. */
    public function cancelOrder(
        AdminOrderActionRequest $request,
        AdminOrderService $service,
        WriteAdminAuditLog $audit
    ): JsonResponse {
        $data = $request->validated();

        $order = Order::query()->findOrFail($data['id']);
        $reason = $data['reason'] ?? $data['note'] ?? null;

        return $this->completeTransition(
            $request,
            $order,
            OrderStatus::Cancelled->value,
            $reason,
            $service,
            $audit
        );
    }

    /** Save staff-only preparation or delivery notes on an order. */
    public function updateOrderInternalNote(AdminOrderActionRequest $request, WriteAdminAuditLog $audit): JsonResponse
    {
        $data = $request->validated();
        $order = Order::query()->where('order_number', $data['order_number'])->firstOrFail();
        $order->internal_note = $data['internal_note'] ?? null;
        $order->save();
        $audit->execute($request, 'order.internal_note_updated', $order);

        return $this->successResponse('Internal note saved.', ['order_number' => $order->order_number, 'internal_note' => $order->internal_note]);
    }

    /** Resolve a fixed route action's order ID and delegate the transition atomically. */
    private function applyFixedTransition(
        AdminOrderActionRequest $request,
        string $status,
        AdminOrderService $service,
        WriteAdminAuditLog $audit
    ): JsonResponse {
        $data = $request->validated();
        $order = Order::query()->findOrFail($data['id']);

        return $this->completeTransition(
            $request,
            $order,
            $status,
            $data['note'] ?? null,
            $service,
            $audit
        );
    }

    private function filteredOrderQuery(
        AdminOrderActionRequest $request,
        AdminDateRangeService $dateRanges
    ): Builder {
        $filters = $request->validated();
        $window = $dateRanges->resolve($filters);
        $sortColumn = $filters['sort_by'] ?? 'placed_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';
        $applyDateWindow = ($filters['queue'] ?? null) !== 'active_or_recent';

        return Order::query()
            ->with('items')
            ->when(
                ($filters['queue'] ?? null) === 'active',
                fn (Builder $query) => $query->whereIn('order_status', [
                    'pending',
                    'accepted',
                    'preparing',
                    'ready',
                    'out_for_delivery',
                ])
            )
            ->when(
                ($filters['queue'] ?? null) === 'completed',
                fn (Builder $query) => $query->whereIn('order_status', [
                    'delivered',
                    'picked_up',
                    'cancelled',
                ])
            )
            ->when(
                ($filters['queue'] ?? null) === 'active_or_recent',
                fn (Builder $query) => $query->where(function (Builder $queueQuery) use ($window): void {
                    $queueQuery->whereIn('order_status', [
                        'pending',
                        'accepted',
                        'preparing',
                        'ready',
                        'out_for_delivery',
                    ]);

                    if ($window['from']) {
                        $queueQuery->orWhere('placed_at', '>=', $window['from']);
                    }
                })
            )
            ->when(
                $request->filled('status'),
                fn (Builder $query) => $query->where(
                    'order_status',
                    $filters['status']
                )
            )
            ->when(
                $request->filled('fulfillment_type'),
                fn (Builder $query) => $query->where(
                    'fulfillment_type',
                    $filters['fulfillment_type']
                )
            )
            ->when(
                $applyDateWindow && $window['from'],
                fn (Builder $query) => $query->where(
                    'placed_at',
                    '>=',
                    $window['from']
                )
            )
            ->when(
                $applyDateWindow && $window['to'],
                fn (Builder $query) => $query->where(
                    'placed_at',
                    '<=',
                    $window['to']
                )
            )
            ->when(
                $request->filled('customer'),
                fn (Builder $query) => $query->where(function (Builder $customerQuery) use ($filters): void {
                    $customerTerm = '%'.$filters['customer'].'%';

                    $customerQuery->where('customer_name', 'like', $customerTerm)
                        ->orWhere('customer_mobile_number', 'like', $customerTerm);
                })
            )
            ->when(
                $request->filled('search'),
                fn (Builder $query) => $query->where(function (Builder $searchQuery) use ($filters): void {
                    $searchTerm = '%'.$filters['search'].'%';

                    $searchQuery->where('order_number', 'like', $searchTerm)
                        ->orWhere('customer_mobile_number', 'like', $searchTerm)
                        ->orWhere('customer_name', 'like', $searchTerm);
                })
            )
            ->orderBy($sortColumn, $sortDirection)
            ->orderBy('id', $sortDirection);
    }

    /** Persist, timestamp and audit a state transition before returning the refreshed order resource. */
    private function completeTransition(
        AdminOrderActionRequest|OrderStatusRequest $request,
        Order $order,
        string $status,
        ?string $note,
        AdminOrderService $service,
        WriteAdminAuditLog $audit
    ): JsonResponse {
        $order = DB::transaction(function () use ($request, $order, $status, $note, $service, $audit): Order {
            $updatedOrder = $service->transition($order, $status, $request->user()->id, $note);
            $audit->execute($request, 'order.status_changed', $updatedOrder, ['status' => $status]);

            return $updatedOrder;
        });

        $this->broadcastOrderChange->execute($order, 'status_updated');

        return $this->successResponse(
            'Order status updated.',
            new OrderResource($order->load([
                'items.addons',
                'items.product:id,main_image_path',
                'items.uploads',
                'histories',
            ]))
        );
    }
}
