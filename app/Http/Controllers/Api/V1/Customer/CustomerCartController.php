<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Requests\Api\V1\Customer\AddCustomerCartItemRequest;
use App\Http\Requests\Api\V1\Customer\RemoveCustomerCartItemRequest;
use App\Http\Requests\Api\V1\Customer\UpdateCustomerCartItemRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/** Customer-facing cart API operations. */
class CustomerCartController extends CustomerApiControllerBase
{
    /** Return the authenticated customer cart and totals. */
    public function getCartDetail(): JsonResponse
    {
        return $this->successResponse('Cart loaded successfully.', $this->cart());
    }

    /** Add a catalog-priced product to the customer cart. */
    public function addCartItem(AddCustomerCartItemRequest $request): JsonResponse
    {
        $user = $this->customerUser();
        $validated = $request->validated();
        $product = DB::table('products')->where('id', $validated['product_id'])->where('is_available', true)->whereNull('deleted_at')->first();

        if (! $product) {
            return $this->errorResponse('Product is unavailable.', [], [], 422);
        }

        $variant = null;
        if (! empty($validated['product_variant_id'])) {
            $variant = DB::table('product_variants')
                ->where('id', $validated['product_variant_id'])
                ->where('product_id', $product->id)
                ->where('is_active', true)
                ->first();
            if (! $variant) {
                return $this->errorResponse('Selected product option is unavailable.', [], [], 422);
            }
        }

        $cart = DB::table('carts')->where('user_id', $user->id)->latest('id')->first();
        $cartId = $cart?->id ?? DB::table('carts')->insertGetId([
            'user_id' => $user->id,
            'fulfillment_type' => $validated['fulfillment_type'] ?? 'delivery',
            'currency' => 'INR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $quantity = (int) ($validated['quantity'] ?? 1);
        // Prices are always read from trusted catalog data; never accept a browser-supplied price.
        $unitPrice = (float) ($variant?->price ?? $product->base_price);
        $lineTotal = $unitPrice * $quantity;

        $itemId = DB::table('cart_items')->insertGetId([
            'cart_id' => $cartId,
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'addon_total' => 0,
            'line_total' => $lineTotal,
            'selected_flavour_id' => null,
            'egg_option' => $validated['egg_option'] ?? null,
            'cake_message' => $validated['cake_message'] ?? null,
            'customization_summary_json' => json_encode(array_filter([
                'variant_name' => $validated['variant_name'] ?? null,
                'flavour_name' => $validated['flavour_name'] ?? null,
                'egg_option' => $validated['egg_option'] ?? null,
            ], fn ($value) => $value !== null && $value !== '')),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->recalculateCart($cartId);

        return $this->successResponse('Item added to cart successfully.', [
            'cart' => $this->cart(),
            'added_item' => ['id' => $itemId, 'product_id' => $product->id, 'quantity' => $quantity, 'line_total' => $lineTotal],
        ]);
    }

    /** Update quantity for a cart line owned by the customer. */
    public function updateCartItem(UpdateCustomerCartItemRequest $request): JsonResponse
    {
        $data = $request->validated();
        $item = DB::table('cart_items')
            ->join('carts', 'carts.id', '=', 'cart_items.cart_id')
            ->where('cart_items.id', $data['cart_item_id'])
            ->where('carts.user_id', $this->customerUser()->id)
            ->select('cart_items.*')
            ->first();
        if (! $item) {
            return $this->errorResponse('Cart item not found.', [], [], 404);
        }
        $quantity = (int) $data['quantity'];
        DB::table('cart_items')->where('id', $item->id)->update([
            'quantity' => $quantity,
            'line_total' => (float) $item->unit_price * $quantity + (float) $item->addon_total,
            'updated_at' => now(),
        ]);
        $this->recalculateCart($item->cart_id);

        return $this->successResponse('Cart item updated successfully.', ['cart' => $this->cart()]);
    }

    /** Remove a cart line owned by the customer. */
    public function removeCartItem(RemoveCustomerCartItemRequest $request): JsonResponse
    {
        $cartItemId = $request->validated('cart_item_id');
        $item = DB::table('cart_items')
            ->join('carts', 'carts.id', '=', 'cart_items.cart_id')
            ->where('cart_items.id', $cartItemId)
            ->where('carts.user_id', $this->customerUser()->id)
            ->select('cart_items.*')
            ->first();
        if (! $item) {
            return $this->errorResponse('Cart item not found.', [], [], 404);
        }
        DB::table('cart_items')->where('id', $item->id)->delete();
        $this->recalculateCart($item->cart_id);

        return $this->successResponse('Cart item removed successfully.', ['cart' => $this->cart()]);
    }

    /** Remove all lines from the authenticated customer cart. */
    public function clearCart(): JsonResponse
    {
        $cart = DB::table('carts')->where('user_id', $this->customerUser()->id)->latest('id')->first();
        if ($cart) {
            DB::table('cart_items')->where('cart_id', $cart->id)->delete();
            $this->recalculateCart($cart->id);
        }

        return $this->successResponse('Cart cleared successfully.', ['cart' => $this->cart()]);
    }
}
