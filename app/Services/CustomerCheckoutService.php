<?php

namespace App\Services;

use App\Enums\CouponType;
use App\Enums\FulfillmentType;
use App\Enums\OrderStatus;
use App\Models\BusinessHoliday;
use App\Models\BusinessSetting;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\CustomerAddress;
use App\Models\DeliverySlot;
use App\Models\OpeningHour;
use App\Models\Order;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Reprice customer carts and enforce catalog, delivery, stock and schedule rules. */
class CustomerCheckoutService
{
    /** Return the signed-in customer's most recent cart, optionally holding a row lock. */
    public function customerCart(int $userId, bool $lock = false): ?Cart
    {
        $query = Cart::query()->where('user_id', $userId)->latest('id');
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /** Validate a code against the current cart and persist the resulting discount. */
    public function applyCouponToCart(int $userId, string $code): array
    {
        return DB::transaction(function () use ($userId, $code): array {
            $cart = $this->customerCart($userId, true);

            if (! $cart || $cart->items()->doesntExist()) {
                throw ValidationException::withMessages([
                    'cart' => ['Add a product before applying a coupon.'],
                ]);
            }

            $coupon = Coupon::query()
                ->where('code', $code)
                ->where('is_active', true)
                ->first();

            if (! $coupon) {
                throw ValidationException::withMessages([
                    'coupon_code' => ['This coupon is unavailable.'],
                ]);
            }

            $subtotal = (float) $cart->items()->sum('line_total');
            $discount = $this->couponDiscount(
                $coupon,
                $subtotal,
                $userId,
                $cart->fulfillment_type ?: FulfillmentType::Delivery->value
            );

            if ($discount <= 0) {
                throw ValidationException::withMessages([
                    'coupon_code' => ['This coupon is not eligible for the current cart or order type.'],
                ]);
            }

            $cart->forceFill([
                'coupon_id' => $coupon->id,
                'subtotal' => $subtotal,
                'discount_total' => $discount,
                'grand_total' => max(
                    0,
                    round($subtotal + (float) $cart->delivery_fee + (float) $cart->tax_total - $discount, 2)
                ),
            ])->save();

            return [
                'coupon_code' => $coupon->code,
                'discount_amount' => $discount,
            ];
        });
    }

    /** Reprice configured cart lines using related catalog records and resolve delivery eligibility. */
    public function buildSummary(int $userId, string $fulfillment, ?int $addressId, ?float $latitude, ?float $longitude): array
    {
        $cart = $this->customerCart($userId);
        if (! $cart || $cart->items()->doesntExist()) {
            throw ValidationException::withMessages(['cart' => ['Your cart is empty.']]);
        }

        $business = BusinessSetting::query()->first();
        $deliveryUnavailable = $fulfillment === FulfillmentType::Delivery->value && ! $business?->delivery_available;
        $takeawayUnavailable = $fulfillment === FulfillmentType::Takeaway->value && ! $business?->takeaway_available;

        if (! $business || $deliveryUnavailable || $takeawayUnavailable) {
            throw ValidationException::withMessages(['fulfillment_type' => ['This order type is currently unavailable.']]);
        }

        $address = null;
        if ($fulfillment === FulfillmentType::Delivery->value && $addressId) {
            $address = CustomerAddress::query()
                ->whereKey($addressId)
                ->where('user_id', $userId)
                ->first();
            if (! $address) {
                throw ValidationException::withMessages(['customer_address_id' => ['The selected address is unavailable.']]);
            }
            $latitude = $address->latitude !== null ? (float) $address->latitude : null;
            $longitude = $address->longitude !== null ? (float) $address->longitude : null;
        }

        $items = [];
        $subtotal = 0.0;
        $requestedStock = [];
        $cartLines = $cart->items()->with([
            'product.category',
            'product.variants',
            'product.flavourOptions.flavour',
            'product.addonOptions.addon',
            'product.customization',
            'product.inventories',
            'variant',
            'flavour',
            'addons.addon',
        ])->get();

        foreach ($cartLines as $line) {
            $product = $line->product;
            $productIsTemporarilyUnavailable = $product
                && $product->sold_out_until
                && $product->sold_out_until->isFuture();

            if (! $product || ! $product->is_available || $productIsTemporarilyUnavailable) {
                throw ValidationException::withMessages(['cart' => ['A product in your cart is no longer available.']]);
            }

            $variant = $line->variant;
            $variantIsUnavailable = $line->product_variant_id
                && (! $variant
                    || ! $variant->is_active
                    || (int) $variant->product_id !== (int) $product->id);

            if ($variantIsUnavailable) {
                throw ValidationException::withMessages(['cart' => ['A selected product option is no longer available.']]);
            }
            $unitPrice = (float) ($variant?->price ?? $product->base_price);

            $flavour = $line->flavour;
            $isConfiguredFlavour = $flavour
                && $flavour->is_active
                && $product->flavourOptions->contains(
                    'product_flavour_id',
                    $flavour->id
                );
            if ($line->selected_flavour_id && ! $isConfiguredFlavour) {
                throw ValidationException::withMessages(['cart' => ['A selected flavour is no longer available.']]);
            }
            $unitPrice += (float) ($flavour?->extra_price ?? 0);

            $addonTotal = 0.0;
            $addonDetails = [];
            foreach ($line->addons as $selectedAddon) {
                $addon = $selectedAddon->addon;
                $isConfiguredAddon = $addon
                    && $addon->is_active
                    && $product->addonOptions->contains(
                        'product_addon_id',
                        $addon->id
                    );
                if (! $isConfiguredAddon) {
                    throw ValidationException::withMessages(['cart' => ['A selected add-on is no longer available.']]);
                }
                $lineTotal = (float) $addon->price * (int) $selectedAddon->quantity;
                $addonTotal += $lineTotal;
                $addonDetails[] = [
                    'id' => (int) $addon->id,
                    'name' => $this->translated($addon->name_translations),
                    'quantity' => (int) $selectedAddon->quantity,
                    'unit_price' => (float) $addon->price,
                    'line_total' => $lineTotal,
                ];
            }

            $quantity = (int) $line->quantity;
            $lineTotal = ($unitPrice * $quantity) + $addonTotal;
            $line->forceFill([
                'unit_price' => $unitPrice,
                'addon_total' => $addonTotal,
                'line_total' => $lineTotal,
            ])->save();
            $subtotal += $unitPrice * $quantity;
            $stockKey = $product->id.':'.($variant?->id ?? 'base');
            $requestedStock[$stockKey] = [
                $product->id,
                $variant?->id,
                ($requestedStock[$stockKey][2] ?? 0) + $quantity,
            ];
            $items[] = [
                'id' => (int) $line->id,
                'name' => $this->translated($product->name_translations),
                'image_url' => $product->image_url,
                'variant' => $variant ? $this->translated($variant->name_translations) : null,
                'flavour' => $flavour ? $this->translated($flavour->name_translations) : null,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'addons' => $addonDetails,
                'addon_total' => $addonTotal,
                'cake_message' => $line->cake_message,
                'line_total' => $lineTotal,
            ];
        }

        foreach ($requestedStock as [$productId, $variantId, $quantity]) {
            $this->assertStock(
                (int) $productId,
                $variantId ? (int) $variantId : null,
                (int) $quantity,
                DB::transactionLevel() > 0
            );
        }

        $addonTotal = array_sum(array_column($items, 'addon_total'));
        $orderValue = $subtotal + $addonTotal;
        $deliveryQuote = null;
        $deliveryFee = 0.0;
        if ($fulfillment === FulfillmentType::Delivery->value) {
            if ($latitude === null || $longitude === null) {
                throw ValidationException::withMessages(['delivery_address' => ['Choose an address with a map location.']]);
            }
            $deliveryQuote = app(DeliveryPricingService::class)->quote($latitude, $longitude);
            $minimum = $deliveryQuote['minimum_order_amount'];
            if ($orderValue < $minimum) {
                throw ValidationException::withMessages(['cart' => ['The delivery minimum is Rs. '.number_format($minimum, 2).'.']]);
            }
            $deliveryFee = $deliveryQuote['delivery_fee'];
        }

        $couponQuery = Coupon::query()->whereKey($cart->coupon_id);
        if (DB::transactionLevel() > 0) {
            $couponQuery->lockForUpdate();
        }
        $coupon = $cart->coupon_id ? $couponQuery->first() : null;
        $discount = $this->couponDiscount($coupon, $orderValue, $userId, $fulfillment);
        // No tax-rate setting exists in the schema, so stale client/cart tax values are never reused.
        $tax = 0.0;
        $grandTotal = max(0, round($orderValue + $deliveryFee + $tax - $discount, 2));
        // The legacy cart screen treats subtotal as item-plus-add-on value; keep that stored convention.
        $cart->forceFill([
            'fulfillment_type' => $fulfillment,
            'delivery_zone_id' => null,
            'customer_address_id' => $fulfillment === FulfillmentType::Delivery->value ? $address?->id : null,
            'subtotal' => $orderValue,
            'addon_total' => $addonTotal,
            'discount_total' => $discount,
            'delivery_fee' => $deliveryFee,
            'tax_total' => $tax,
            'grand_total' => $grandTotal,
        ])->save();

        return [
            'items' => $items,
            'totals' => [
                'subtotal' => round($subtotal, 2),
                'addon_total' => round($addonTotal, 2),
                'discount_total' => round($discount, 2),
                'delivery_fee' => round($deliveryFee, 2),
                'tax_total' => $tax,
                'grand_total' => $grandTotal,
            ],
            'fulfillment_type' => $fulfillment,
            'minimum_order_met' => true,
            'delivery' => $fulfillment === FulfillmentType::Delivery->value ? [
                'is_available' => true,
                'distance_km' => $deliveryQuote['distance_km'],
                'max_delivery_distance_km' => $deliveryQuote['max_delivery_distance_km'],
                'distance_basis' => $deliveryQuote['distance_basis'],
                'estimated_minutes' => $deliveryQuote['estimated_delivery_minutes'],
                'fee' => round($deliveryFee, 2),
            ] : [
                'is_available' => true,
                'fee' => 0,
            ],
        ];
    }

    /** Return slots meeting configured hours, notice, horizon and capacity for the active cart. */
    public function availableSchedules(string $fulfillment, int $days = 7, bool $lockOrders = false, ?int $userId = null): array
    {
        $business = BusinessSetting::query()->first();
        $minimumLeadMinutes = (int) ($business?->default_preparation_minutes ?? 15);
        $maximumDays = 14;
        if ($userId && ($cart = $this->customerCart($userId))) {
            $lines = $cart->items()->with(['product.customization', 'product.variants', 'variant'])->get();
            foreach ($lines as $line) {
                $product = $line->product;
                if (! $product) {
                    continue;
                }
                $variant = $line->variant;
                $variantPreparationMinutes = (int) (
                    $variant?->min_preparation_minutes
                    ?? $product->min_preparation_minutes
                );
                $customizationNoticeMinutes = (int) (
                    $product->customization?->min_notice_minutes ?? 0
                );
                $minimumLeadMinutes = max(
                    $minimumLeadMinutes,
                    $variantPreparationMinutes,
                    $customizationNoticeMinutes
                );
                if ($product->customization?->max_schedule_days) {
                    $maximumDays = min($maximumDays, (int) $product->customization->max_schedule_days);
                }
            }
        }

        $days = min(max($days, 1), $maximumDays);
        $slots = DeliverySlot::query()
            ->where('fulfillment_type', $fulfillment)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
        $available = [];
        for ($offset = 0; $offset <= $days; $offset++) {
            $date = now()->startOfDay()->addDays($offset);
            $holiday = BusinessHoliday::query()->whereDate('holiday_date', $date->toDateString())->first();
            $hours = OpeningHour::query()->where('day_of_week', $date->dayOfWeek)->first();
            $isOpen = $holiday ? ! $holiday->is_closed : ($hours && ! $hours->is_closed);
            $opensAt = $holiday && $holiday->opens_at ? $holiday->opens_at : ($hours?->opens_at);
            $closesAt = $holiday && $holiday->closes_at ? $holiday->closes_at : ($hours?->closes_at);
            if (! $isOpen || ! $opensAt || ! $closesAt) {
                continue;
            }

            foreach ($slots as $slot) {
                if ($slot->starts_at < $opensAt || $slot->ends_at > $closesAt) {
                    continue;
                }
                $slotStart = Carbon::parse($date->toDateString().' '.$slot->starts_at);
                if ($slotStart->lt(now()->addMinutes($minimumLeadMinutes))) {
                    continue;
                }

                $ordersQuery = Order::query()
                    ->where('fulfillment_type', $fulfillment)
                    ->whereDate('scheduled_for', $date->toDateString())
                    ->whereTime('scheduled_for', '>=', $slot->starts_at)
                    ->whereTime('scheduled_for', '<', $slot->ends_at)
                    ->whereNotIn('order_status', [OrderStatus::Cancelled->value, OrderStatus::Rejected->value]);
                $orders = $lockOrders
                    ? $ordersQuery->lockForUpdate()->get(['id'])->count()
                    : $ordersQuery->count();
                if ($slot->max_orders !== null && $orders >= $slot->max_orders) {
                    continue;
                }

                $available[] = [
                    'value' => $slotStart->toIso8601String(),
                    'date' => $date->toDateString(),
                    'label' => $date->isToday() ? 'Today' : $date->format('D, d M'),
                    'slot' => Carbon::parse($slot->starts_at)->format('g:i A').' - '.Carbon::parse($slot->ends_at)->format('g:i A'),
                    'remaining_capacity' => $slot->max_orders === null
                        ? null
                        : max(0, (int) $slot->max_orders - $orders),
                ];
            }
        }

        return $available;
    }

    /** Lock the selected fulfillment slot and recheck its hours, notice and capacity. */
    public function lockAndAssertSchedule(string $fulfillment, Carbon $scheduledFor, int $userId): void
    {
        $time = $scheduledFor->format('H:i:s');
        $slot = DeliverySlot::query()
            ->where('fulfillment_type', $fulfillment)
            ->where('is_active', true)
            ->where('starts_at', '<=', $time)
            ->where('ends_at', '>', $time)
            ->lockForUpdate()
            ->first();
        if (! $slot || Carbon::parse($slot->starts_at)->format('H:i:s') !== $time) {
            throw ValidationException::withMessages(['scheduled_for' => ['Choose the start time of an available pickup or delivery slot.']]);
        }

        $availableOptions = $this->availableSchedules($fulfillment, 14, true, $userId);
        $valid = collect($availableOptions)->contains(
            fn ($option) => Carbon::parse($option['value'])->equalTo($scheduledFor)
        );
        if (! $valid) {
            throw ValidationException::withMessages([
                'scheduled_for' => [
                    'This order time is outside opening hours or the slot has reached capacity. Please select another time.',
                ],
            ]);
        }
    }

    /** Reject sold-out or insufficient tracked inventory for a product option. */
    public function assertStock(int $productId, ?int $variantId, int $quantity, bool $lock = false): void
    {
        $product = Product::query()->find($productId);
        if (! $product) {
            throw ValidationException::withMessages(['cart' => ['A product in your cart is no longer available.']]);
        }
        $query = $product->inventories()->where(function ($builder) use ($variantId) {
            if ($variantId) {
                $builder->where('product_variant_id', $variantId);
            } else {
                $builder->whereNull('product_variant_id');
            }
        });
        if ($lock) {
            $query->lockForUpdate();
        }
        $inventory = $query->first();
        if (! $inventory && $variantId) {
            $baseInventory = $product->inventories()->whereNull('product_variant_id');
            if ($lock) {
                $baseInventory->lockForUpdate();
            }
            $inventory = $baseInventory->first();
        }
        $isTemporarilySoldOut = $inventory?->sold_out_until?->isFuture() ?? false;
        $hasInsufficientStock = $inventory
            && $inventory->track_stock
            && ($inventory->stock_quantity === null
                || (int) $inventory->stock_quantity < $quantity);

        if ($inventory && ($inventory->is_sold_out || $isTemporarilySoldOut || $hasInsufficientStock)) {
            throw ValidationException::withMessages(['cart' => ['A product in your cart does not have enough stock.']]);
        }
    }

    /** Check coupon dates, fulfillment, thresholds, usage limits and configured discount caps. */
    private function couponDiscount(?Coupon $coupon, float $subtotal, int $userId, string $fulfillment): float
    {
        $fulfillmentDoesNotMatch = $coupon?->applies_to_fulfillment_type
            && ! in_array($coupon->applies_to_fulfillment_type, [$fulfillment, 'both'], true);
        $couponHasNotStarted = $coupon?->starts_at && now()->lt($coupon->starts_at);
        $couponHasExpired = $coupon?->ends_at && now()->gt($coupon->ends_at);
        $minimumOrderNotMet = $coupon?->minimum_order_amount
            && $subtotal < $coupon->minimum_order_amount;

        if (! $coupon
            || ! $coupon->is_active
            || $fulfillmentDoesNotMatch
            || $couponHasNotStarted
            || $couponHasExpired
            || $minimumOrderNotMet) {
            return 0.0;
        }
        $allRedemptions = $coupon->redemptions();
        $customerRedemptions = $coupon->redemptions()->where('user_id', $userId);
        if (DB::transactionLevel() > 0) {
            $allRedemptions->lockForUpdate();
            $customerRedemptions->lockForUpdate();
        }
        if ($coupon->usage_limit !== null && $allRedemptions->get(['id'])->count() >= $coupon->usage_limit) {
            return 0.0;
        }
        if ($coupon->usage_limit_per_customer !== null && $customerRedemptions->get(['id'])->count() >= $coupon->usage_limit_per_customer) {
            return 0.0;
        }
        $discount = $coupon->discount_type === CouponType::Percentage->value ? $subtotal * (float) $coupon->discount_value / 100 : (float) $coupon->discount_value;
        if ($coupon->max_discount_amount !== null) {
            $discount = min($discount, (float) $coupon->max_discount_amount);
        }

        return round(min($subtotal, $discount), 2);
    }

    /** Resolve the best available customer-facing translation for a localized label. */
    public function translated(mixed $translations): string
    {
        if (is_string($translations)) {
            $translations = json_decode($translations, true) ?: [];
        }
        if (! is_array($translations)) {
            return (string) $translations;
        }

        return (string) ($translations[app()->getLocale()] ?? $translations['gu'] ?? $translations['en'] ?? $translations['hi'] ?? reset($translations) ?: '');
    }
}
