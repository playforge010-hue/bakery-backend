<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Actions\BroadcastOrderChange;
use App\Enums\OrderStatus;
use App\Http\Requests\Api\V1\Customer\GetCustomerOrderDetailRequest;
use App\Http\Requests\Api\V1\Customer\CancelCustomerOrderRequest;
use App\Http\Requests\Api\V1\Customer\ReorderCustomerOrderRequest;
use App\Models\OrderItem;
use App\Models\Order;
use App\Services\Admin\AdminOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Customer-facing order API operations. */
class CustomerOrderController extends CustomerApiControllerBase
{
    /** Return current cart data and checkout capability flags. */
    public function validateCheckout(): JsonResponse
    {
        return $this->successResponse('Checkout validated successfully.', [
            'cart' => $this->cart(),
            'delivery' => [
                'is_available' => true,
                'estimated_delivery_minutes' => 45,
            ],
            'payment_methods' => ['UPI', 'Card', 'Cash'],
        ]);
    }

    /** Return order history cards for the authenticated customer. */
    public function getOrderList(): JsonResponse
    {
        return $this->successResponse('Order history loaded successfully.', $this->orders());
    }

    /** Return detail and tracking history for a customer-owned order. */
    public function getOrderDetail(GetCustomerOrderDetailRequest $request): JsonResponse
    {
        $orderNumber = $request->validated('order_number');
        $order = DB::table('orders')->where('order_number', $orderNumber)->where('user_id', $this->customerUser()->id)->whereNull('deleted_at')->first();

        if (! $order) {
            return $this->errorResponse('Order not found.', [], [], 404);
        }

        $history = DB::table('order_status_histories')->where('order_id', $order->id)->orderBy('id')->get();
        $items = OrderItem::query()
            ->with('product:id,main_image_path')
            ->where('order_id', $order->id)
            ->get();

        return $this->successResponse('Order detail loaded successfully.', [
            'order' => [
                'order_number' => $order->order_number,
                'status' => Str::headline($order->order_status),
                'placed_at' => $order->placed_at,
                'total' => (float) $order->grand_total,
                'fulfillment_type' => Str::headline($order->fulfillment_type),
                'payment_status' => Str::headline($order->payment_status),
                'delivery_address' => json_decode($order->delivery_address_json ?: 'null', true),
                'pickup_address' => json_decode($order->pickup_address_json ?: 'null', true),
                'scheduled_for' => $order->scheduled_for,
            ],
            'items' => $items->map(fn (OrderItem $item) => [
                'name' => $item->product_name,
                'variant' => $item->variant_name,
                'quantity' => (int) $item->quantity,
                'line_total' => (float) $item->line_total,
                'image_url' => $item->product?->main_image_url,
            ])->values(),
            'timeline' => $history->map(fn ($item) => [
                'label' => Str::headline($item->new_status),
                'description' => $item->note,
                'is_done' => true,
                'created_at' => $item->created_at,
            ])->values(),
        ]);
    }

    /** Cancel a customer order while its current state permits cancellation. */
    public function cancelOrder(
        CancelCustomerOrderRequest $request,
        AdminOrderService $orderService,
        BroadcastOrderChange $broadcastOrderChange
    ): JsonResponse
    {
        $data = $request->validated();
        $order = Order::query()
            ->where('order_number', $data['order_number'])
            ->where('user_id', $this->customerUser()->id)
            ->first();

        if (! $order) {
            return $this->errorResponse('Order not found.', [], [], 404);
        }

        if (! in_array($order->order_status, [OrderStatus::Pending->value, OrderStatus::Confirmed->value], true)) {
            return $this->errorResponse('This order can no longer be cancelled.', [], [], 422);
        }

        $order = $orderService->transition(
            $order,
            OrderStatus::Cancelled->value,
            $request->user()->id,
            $data['reason'] ?? 'Cancelled by customer',
            [OrderStatus::Pending->value, OrderStatus::Confirmed->value]
        );

        $broadcastOrderChange->execute($order, 'status_updated');

        return $this->successResponse('Order cancelled successfully.', [
            'order_number' => $order->order_number,
            'status' => 'Cancelled',
        ]);
    }

    /** Copy a previous customer order into the current cart. */
    public function reorderOrder(ReorderCustomerOrderRequest $request): JsonResponse
    {
        $order = DB::table('orders')->where('order_number', $request->validated('order_number'))->where('user_id', $this->customerUser()->id)->first();
        if (! $order) {
            return $this->errorResponse('Order not found.', [], [], 404);
        }
        $cart = DB::table('carts')->where('user_id', $this->customerUser()->id)->latest('id')->first();
        $cartId = $cart?->id ?? DB::table('carts')->insertGetId([
            'user_id' => $this->customerUser()->id,
            'currency' => 'INR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        foreach (DB::table('order_items')->where('order_id', $order->id)->whereNotNull('product_id')->get() as $item) {
            DB::table('cart_items')->insert([
                'cart_id' => $cartId,
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'addon_total' => $item->addon_total,
                'line_total' => $item->line_total,
                'egg_option' => $item->egg_option,
                'cake_message' => $item->cake_message,
                'customization_summary_json' => $item->customization_summary_json,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $this->recalculateCart($cartId);

        return $this->successResponse('Previous order added to cart successfully.', ['order_number' => $order->order_number, 'cart' => $this->cart()]);
    }
}
