<?php

namespace App\Actions\Customer;

use App\Actions\BroadcastOrderChange;
use App\Enums\FulfillmentType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\BusinessSetting;
use App\Models\Cart;
use App\Models\CouponRedemption;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderItemAddon;
use App\Services\CustomerCheckoutService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Place an order from locked, repriced cart data with immutable item/address snapshots. */
class PlaceCustomerOrder
{
    /** Inject checkout validation and the post-commit order event publisher. */
    public function __construct(
        private readonly CustomerCheckoutService $checkout,
        private readonly BroadcastOrderChange $broadcastOrderChange
    ) {
    }

    /** Lock cart, validate stock and capacity, snapshot all order data and clear cart atomically. */
    public function execute(object $user, array $data): Order
    {
        $order = DB::transaction(function () use ($user, $data) {
            $cart = Cart::query()->where('user_id', $user->id)->latest('id')->lockForUpdate()->first();
            if (! $cart || $cart->items()->doesntExist()) {
                throw ValidationException::withMessages(['cart' => ['Your cart is empty.']]);
            }
            $cart->load('coupon');
            $deliveryLatitude = isset($data['delivery_latitude'])
                ? (float) $data['delivery_latitude']
                : null;
            $deliveryLongitude = isset($data['delivery_longitude'])
                ? (float) $data['delivery_longitude']
                : null;
            $summary = $this->checkout->buildSummary(
                $user->id,
                $data['fulfillment_type'],
                $data['customer_address_id'] ?? null,
                $deliveryLatitude,
                $deliveryLongitude
            );
            $schedule = Carbon::parse($data['scheduled_for']);
            $this->checkout->lockAndAssertSchedule($data['fulfillment_type'], $schedule, (int) $user->id);
            $settings = BusinessSetting::query()->first();
            if (! $settings) {
                throw ValidationException::withMessages(['order' => ['Bakery ordering is currently unavailable.']]);
            }
            $paymentMethod = $data['payment_method'];
            if ($paymentMethod === PaymentMethod::Cash->value && ! $settings->cod_available) {
                throw ValidationException::withMessages(['payment_method' => ['Cash payment is currently unavailable.']]);
            }
            if ($paymentMethod !== PaymentMethod::Cash->value) {
                throw ValidationException::withMessages([
                    'payment_method' => ['Online payment is unavailable until a payment provider is connected.'],
                ]);
            }
            $addressSnapshot = null;
            $zoneId = null;
            if ($data['fulfillment_type'] === FulfillmentType::Delivery->value) {
                $savedAddress = null;
                if (! empty($data['customer_address_id'])) {
                    $savedAddress = CustomerAddress::query()
                        ->whereKey($data['customer_address_id'])
                        ->where('user_id', $user->id)
                        ->first();
                }

                if ($savedAddress) {
                    $addressSnapshot = [
                        'label' => $savedAddress->label,
                        'receiver_name' => $savedAddress->receiver_name,
                        'receiver_mobile_number' => $savedAddress->receiver_mobile_number,
                        'address_line_1' => $savedAddress->address_line_1,
                        'address_line_2' => $savedAddress->address_line_2,
                        'landmark' => $savedAddress->landmark,
                        'area' => $savedAddress->area,
                        'city' => $savedAddress->city,
                        'state' => $savedAddress->state,
                        'pincode' => $savedAddress->pincode,
                        'latitude' => $savedAddress->latitude !== null
                            ? (float) $savedAddress->latitude
                            : (float) $data['delivery_latitude'],
                        'longitude' => $savedAddress->longitude !== null
                            ? (float) $savedAddress->longitude
                            : (float) $data['delivery_longitude'],
                    ];
                } else {
                    $addressSnapshot = [
                        'label' => $data['delivery_address_label'],
                        'address_line_1' => $data['delivery_address_text'],
                        'latitude' => (float) $data['delivery_latitude'],
                        'longitude' => (float) $data['delivery_longitude'],
                    ];
                }
            }
            $pickupSnapshot = null;
            if ($data['fulfillment_type'] === FulfillmentType::Takeaway->value) {
                $pickupSnapshot = [
                    'name' => $settings->bakery_name,
                    'address_line_1' => $settings->address_line_1,
                    'address_line_2' => $settings->address_line_2,
                    'area' => $settings->area,
                    'city' => $settings->city,
                    'state' => $settings->state,
                    'pincode' => $settings->pincode,
                    'latitude' => $settings->latitude,
                    'longitude' => $settings->longitude,
                ];
            }
            $total = $summary['totals'];
            $order = Order::query()->create([
                'order_number' => 'DB-'.now()->format('ymdHis').'-'.strtoupper(Str::random(5)),
                'user_id' => $user->id,
                'customer_name' => $user->name,
                'customer_mobile_number' => $user->mobile_number,
                'customer_email' => $user->email,
                'fulfillment_type' => $data['fulfillment_type'],
                'order_status' => OrderStatus::Confirmed->value,
                'payment_status' => PaymentStatus::Pending->value,
                'payment_method' => $paymentMethod,
                'delivery_zone_id' => $zoneId,
                'delivery_address_json' => $addressSnapshot,
                'pickup_address_json' => $pickupSnapshot,
                'scheduled_for' => $schedule,
                'placed_at' => now(),
                'accepted_at' => now(),
                'subtotal' => $total['subtotal'],
                'addon_total' => $total['addon_total'],
                'discount_total' => $total['discount_total'],
                'delivery_fee' => $total['delivery_fee'],
                'tax_total' => $total['tax_total'],
                'grand_total' => $total['grand_total'],
                'customer_note' => $data['customer_note'] ?? null,
                'coupon_id' => $cart->coupon_id,
                'coupon_code' => $cart->coupon?->code,
            ]);

            foreach ($cart->items()->with(['product', 'variant', 'flavour', 'addons.addon', 'uploads'])->get() as $line) {
                $product = $line->product;
                $variant = $line->variant;
                $flavour = $line->flavour;
                $orderItem = $order->items()->create([
                    'product_id' => $line->product_id,
                    'product_variant_id' => $line->product_variant_id,
                    'product_name' => $this->checkout->translated($product->name_translations),
                    'variant_name' => $variant ? $this->checkout->translated($variant->name_translations) : null,
                    'sku' => $variant?->sku ?? $product->sku,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unit_price,
                    'addon_total' => $line->addon_total,
                    'line_total' => $line->line_total,
                    'selected_flavour_name' => $flavour ? $this->checkout->translated($flavour->name_translations) : null,
                    'egg_option' => $line->egg_option,
                    'cake_message' => $line->cake_message,
                    'customization_summary_json' => $line->customization_summary_json,
                ]);
                foreach ($line->addons as $cartAddon) {
                    OrderItemAddon::query()->create([
                        'order_item_id' => $orderItem->id,
                        'product_addon_id' => $cartAddon->product_addon_id,
                        'addon_name' => $this->checkout->translated($cartAddon->addon?->name_translations),
                        'quantity' => $cartAddon->quantity,
                        'unit_price' => $cartAddon->unit_price,
                        'line_total' => $cartAddon->line_total,
                    ]);
                }
                foreach ($line->uploads as $upload) {
                    $upload->copyToOrderItem($orderItem);
                }

                $inventoryQuery = $product->inventories()->where(function ($query) use ($line) {
                    $line->product_variant_id ? $query->where('product_variant_id', $line->product_variant_id) : $query->whereNull('product_variant_id');
                })->lockForUpdate();
                $inventoryRows = $inventoryQuery->get();
                if ($inventoryRows->isEmpty() && $line->product_variant_id) {
                    $inventoryRows = $product->inventories()->whereNull('product_variant_id')->lockForUpdate()->get();
                }
                foreach ($inventoryRows as $inventory) {
                    if ($inventory->track_stock) {
                        $inventory->decrement('stock_quantity', (int) $line->quantity);
                    }
                }
            }
            $order->statusHistory()->create(['new_status' => OrderStatus::Confirmed->value, 'note' => 'Order confirmed', 'visible_to_customer' => true]);
            $order->payments()->create(['provider' => $paymentMethod, 'amount' => $total['grand_total'], 'currency' => 'INR', 'status' => PaymentStatus::Pending->value]);
            if ($cart->coupon_id && $total['discount_total'] > 0) {
                CouponRedemption::query()->create([
                    'coupon_id' => $cart->coupon_id,
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                    'code' => $order->coupon_code,
                    'discount_amount' => $total['discount_total'],
                ]);
            }
            $cart->items()->delete();
            $cart->update([
                'subtotal' => 0,
                'addon_total' => 0,
                'discount_total' => 0,
                'delivery_fee' => 0,
                'tax_total' => 0,
                'grand_total' => 0,
                'coupon_id' => null,
                'scheduled_for' => null,
                'updated_at' => now(),
            ]);

            return $order;
        }, 3);

        $this->broadcastOrderChange->execute($order, 'created');

        return $order;
    }
}
