<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Services\CustomerCatalogService;
use App\Support\ImagePath;
use App\Traits\ApiResponseTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Shared response and customer-owned data helpers for customer API controllers. */
abstract class CustomerApiControllerBase extends Controller
{
    use ApiResponseTrait;

    /** Inject shared catalog translation and projection services. */
    public function __construct(protected readonly CustomerCatalogService $catalog)
    {
    }

    /** Return the bakery's configured place labels for location search hints. */
    protected function deliveryAreas(): array
    {
        $business = BusinessSetting::query()->first();

        return collect([$business?->area, $business?->city])
            ->filter()
            ->unique()
            ->map(fn (string $name) => [
                'name' => $name,
                'latitude' => $business?->latitude !== null ? (float) $business->latitude : null,
                'longitude' => $business?->longitude !== null ? (float) $business->longitude : null,
            ])
            ->values()
            ->all();
    }

    /** Return saved address summaries for the authenticated customer. */
    protected function addresses(): array
    {
        $user = $this->customerUser();

        return DB::table('customer_addresses')
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->orderByDesc('is_default')
            ->get()
            ->map(fn ($address) => [
                'id' => $address->id,
                'label' => $address->label,
                'receiver_name' => $address->receiver_name,
                'address' => trim($address->address_line_1.', '.$address->area.', '.$address->city.', '.$address->state.' '.$address->pincode),
                'latitude' => $address->latitude !== null ? (float) $address->latitude : null,
                'longitude' => $address->longitude !== null ? (float) $address->longitude : null,
                'is_default' => (bool) $address->is_default,
            ])
            ->values()
            ->all();
    }

    /** Return the authenticated customer's cart and frontend totals. */
    protected function cart(): array
    {
        $user = $this->customerUser();
        $cart = DB::table('carts')->where('user_id', $user->id)->latest('id')->first();

        if (! $cart) {
            return [
                'items' => [],
                'coupon_code' => null,
                'totals' => [
                    'subtotal' => 0,
                    'delivery_fee' => 0,
                    'discount_total' => 0,
                    'tax_total' => 0,
                    'grand_total' => 0,
                ],
            ];
        }

        $items = DB::table('cart_items')
            ->join('products', 'products.id', '=', 'cart_items.product_id')
            ->where('cart_items.cart_id', $cart->id)
            ->select(
                'cart_items.*',
                'products.id as product_id',
                'products.slug as product_slug',
                'products.name_translations',
                'products.main_image_path'
            )
            ->get()
            ->values();

        $selectedAddons = DB::table('cart_item_addons')
            ->join('cart_items', 'cart_items.id', '=', 'cart_item_addons.cart_item_id')
            ->join('product_addons', 'product_addons.id', '=', 'cart_item_addons.product_addon_id')
            ->where('cart_items.cart_id', $cart->id)
            ->select(
                'cart_items.id as cart_item_id',
                'product_addons.id as addon_id',
                'product_addons.name_translations',
                'cart_item_addons.quantity',
                'cart_item_addons.line_total'
            )
            ->get()
            ->groupBy('cart_item_id');

        $items = $items->map(fn ($item) => [
            'id' => $item->id,
            'product_id' => (int) $item->product_id,
            'slug' => $item->product_slug,
            'name' => $this->catalog->translated($item->name_translations),
            'image_url' => ImagePath::url($item->main_image_path),
            'unit_price' => (float) $item->unit_price,
            'addon_total' => (float) $item->addon_total,
            'variant' => collect(json_decode($item->customization_summary_json ?: '{}', true))->filter()->implode(' · '),
            'product_variant_id' => $item->product_variant_id,
            'flavour_id' => $item->selected_flavour_id,
            'egg_option' => $item->egg_option,
            'quantity' => $item->quantity,
            'line_total' => (float) $item->line_total,
            'cake_message' => $item->cake_message,
            'addons' => $selectedAddons->get($item->id, collect())
                ->map(fn ($addon) => [
                    'id' => (int) $addon->addon_id,
                    'name' => $this->catalog->translated($addon->name_translations),
                    'quantity' => (int) $addon->quantity,
                    'line_total' => (float) $addon->line_total,
                ])
                ->values(),
        ])
            ->values();

        return [
            'items' => $items,
            'coupon_code' => $cart->coupon_id
                ? DB::table('coupons')->where('id', $cart->coupon_id)->value('code')
                : null,
            'totals' => [
                'subtotal' => (float) $cart->subtotal,
                'delivery_fee' => (float) $cart->delivery_fee,
                'discount_total' => (float) $cart->discount_total,
                'tax_total' => (float) $cart->tax_total,
                'grand_total' => (float) $cart->grand_total,
            ],
        ];
    }

    /** Recalculate persisted cart totals after a cart-line mutation. */
    protected function recalculateCart(int $cartId): void
    {
        $subtotal = (float) DB::table('cart_items')->where('cart_id', $cartId)->sum('line_total');
        $cart = DB::table('carts')->where('id', $cartId)->first();
        $deliveryFee = (float) ($cart->delivery_fee ?? 0);
        $tax = (float) ($cart->tax_total ?? 0);

        // A changed basket must qualify for its coupon again before a discount is shown.
        DB::table('carts')->where('id', $cartId)->update([
            'subtotal' => $subtotal,
            'coupon_id' => null,
            'discount_total' => 0,
            'grand_total' => max(0, $subtotal + $deliveryFee + $tax),
            'updated_at' => now(),
        ]);
    }

    /** Return order summary cards owned by the authenticated customer. */
    protected function orders(?int $perPage = null, int $page = 1, array $filters = []): array
    {
        $user = $this->customerUser();
        $ordersQuery = DB::table('orders')
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->orderByDesc('placed_at');

        $finishedStatuses = ['delivered', 'picked_up', 'cancelled', 'rejected'];

        if (($filters['filter'] ?? 'all') === 'active') {
            $ordersQuery->whereNotIn('order_status', $finishedStatuses);
        } elseif (($filters['filter'] ?? 'all') === 'past') {
            $ordersQuery->whereIn('order_status', $finishedStatuses);
        }

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);

            $ordersQuery->where(function ($query) use ($search) {
                $query->where('order_number', 'like', '%'.$search.'%')
                    ->orWhere('order_status', 'like', '%'.$search.'%')
                    ->orWhereExists(function ($itemQuery) use ($search) {
                        $itemQuery->selectRaw('1')
                            ->from('order_items')
                            ->whereColumn('order_items.order_id', 'orders.id')
                            ->where('order_items.product_name', 'like', '%'.$search.'%');
                    });
            });
        }

        $paginator = $perPage === null
            ? null
            : $ordersQuery->paginate($perPage, ['*'], 'page', $page);
        $orders = $paginator?->getCollection() ?? $ordersQuery->get();

        if ($orders->isEmpty()) {
            $orderCards = [];
        } else {
            $itemsByOrder = DB::table('order_items')
                ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
                ->whereIn('order_items.order_id', $orders->pluck('id'))
                ->select(
                    'order_items.id',
                    'order_items.order_id',
                    'order_items.product_name',
                    'order_items.quantity',
                    'order_items.line_total',
                    'order_items.variant_name',
                    'products.main_image_path'
                )
                ->get()
                ->groupBy('order_id');

            $orderCards = $orders
                ->map(function ($order) use ($itemsByOrder) {
                    $orderItems = $itemsByOrder->get($order->id, collect());

                    return [
                        'order_number' => $order->order_number,
                        'status' => Str::headline($order->order_status),
                        'placed_at' => $order->placed_at,
                        'total' => (float) $order->grand_total,
                        'items' => $orderItems->pluck('product_name')->implode(', '),
                        'items_count' => (int) $orderItems->sum('quantity'),
                        'item_details' => $orderItems->map(fn ($item) => [
                            'id' => (int) $item->id,
                            'name' => $item->product_name,
                            'variant' => $item->variant_name,
                            'quantity' => (int) $item->quantity,
                            'line_total' => (float) $item->line_total,
                            'image_url' => ImagePath::url($item->main_image_path),
                        ])->values(),
                        'fulfillment_type' => Str::headline($order->fulfillment_type),
                    ];
                })
                ->values()
                ->all();
        }

        if ($paginator === null) {
            return $orderCards;
        }

        return [
            'items' => $orderCards,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /** Resolve the current authenticated customer for ownership checks. */
    protected function customerUser(): object
    {
        return request()->user();
    }
}
