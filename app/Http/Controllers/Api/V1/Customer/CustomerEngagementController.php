<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Requests\Api\V1\Customer\CreateCustomerSupportTicketRequest;
use App\Http\Requests\Api\V1\Customer\ApplyCustomerCouponRequest;
use App\Http\Requests\Api\V1\Customer\UpdateCustomerFavoriteRequest;
use App\Services\CustomerCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Customer-facing engagement API operations. */
class CustomerEngagementController extends CustomerApiControllerBase
{
    /** Check an active coupon and return the current cart summary. */
    public function applyCoupon(
        ApplyCustomerCouponRequest $request,
        CustomerCheckoutService $checkout
    ): JsonResponse
    {
        $result = $checkout->applyCouponToCart(
            $this->customerUser()->id,
            $request->validated('coupon_code')
        );

        return $this->successResponse('Coupon applied successfully.', [
            ...$result,
            'cart' => $this->cart(),
        ]);
    }

    /** Return products saved by the authenticated customer. */
    public function getFavoriteList(): JsonResponse
    {
        $user = $this->customerUser();
        $products = DB::table('customer_favorites')
            ->join('products', 'products.id', '=', 'customer_favorites.product_id')
            ->where('customer_favorites.user_id', $user->id)
            ->whereNull('products.deleted_at')
            ->select('products.*')
            ->get()
            ->map(fn ($product) => $this->catalog->productCard($product))
            ->values();

        return $this->successResponse('Favorite products loaded successfully.', $products);
    }

    /** Add or remove a product from customer favorites. */
    public function updateFavorite(UpdateCustomerFavoriteRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $userId = $this->customerUser()->id;
        if ($validated['is_favorite']) {
            DB::table('customer_favorites')->updateOrInsert(
                ['user_id' => $userId, 'product_id' => $validated['product_id']],
                ['created_at' => now(), 'updated_at' => now()],
            );
        } else {
            DB::table('customer_favorites')->where('user_id', $userId)->where('product_id', $validated['product_id'])->delete();
        }

        return $this->successResponse('Favorites updated successfully.', [
            'product_id' => (int) $validated['product_id'],
            'is_favorite' => (bool) $validated['is_favorite'],
        ]);
    }

    /** Create a support ticket and its initial customer message. */
    public function createSupportTicket(CreateCustomerSupportTicketRequest $request): JsonResponse
    {
        $user = $this->customerUser();
        $validated = $request->validated();
        $ticketNumber = 'SUP-'.now()->format('ymdHis').'-'.Str::upper(Str::random(4));
        $order = ! empty($validated['order_number'])
            ? DB::table('orders')->where('order_number', $validated['order_number'])->where('user_id', $user->id)->first()
            : null;
        if (! empty($validated['order_number']) && ! $order) {
            return $this->errorResponse('Order not found.', [], [], 404);
        }
        $ticketId = DB::table('support_tickets')->insertGetId([
            'ticket_number' => $ticketNumber,
            'user_id' => $user->id,
            'order_id' => $order?->id,
            'subject' => $validated['subject'],
            'status' => 'open',
            'priority' => 'normal',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('support_messages')->insert([
            'support_ticket_id' => $ticketId,
            'sender_user_id' => $user->id,
            'sender_type' => 'customer',
            'message' => $validated['message'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->successResponse('Support ticket created successfully.', [
            'ticket_number' => $ticketNumber,
            'subject' => $validated['subject'],
            'status' => 'open',
            'category' => $validated['category'],
        ], [], 201);
    }
}
