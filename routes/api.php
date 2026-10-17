<?php

use App\Http\Controllers\Api\V1\Admin\AdminAuthController;
use App\Http\Controllers\Api\V1\Admin\AdminBusinessController;
use App\Http\Controllers\Api\V1\Admin\AdminCatalogController;
use App\Http\Controllers\Api\V1\Admin\AdminContentController;
use App\Http\Controllers\Api\V1\Admin\AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\AdminDeliveryPricingController;
use App\Http\Controllers\Api\V1\Admin\AdminOperationsController;
use App\Http\Controllers\Api\V1\Admin\AdminOrderController;
use App\Http\Controllers\Api\V1\Admin\AdminSupportController;
use App\Http\Controllers\Api\V1\Customer\CustomerAddressController;
use App\Http\Controllers\Api\V1\Customer\CustomerCartController;
use App\Http\Controllers\Api\V1\Customer\CustomerCatalogController;
use App\Http\Controllers\Api\V1\Customer\CustomerCheckoutController;
use App\Http\Controllers\Api\V1\Customer\CustomerDeliveryPricingController;
use App\Http\Controllers\Api\V1\Customer\CustomerEngagementController;
use App\Http\Controllers\Api\V1\Customer\CustomerIdentityController;
use App\Http\Controllers\Api\V1\Customer\CustomerLocationController;
use App\Http\Controllers\Api\V1\Customer\CustomerOrderController;
use App\Http\Controllers\Api\V1\Customer\CustomerSupportController;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

Route::get('/v1/health', function () {
    return [
        'success' => true,
        'message' => 'Dwarkesh Bakers API is ready.',
        'data' => ['service' => 'dwarkesh-bakers-api'],
        'meta' => (object) [],
    ];
});

Route::get('/v1/health/database', function () {
    try {
        DB::select('SELECT 1');

        $requiredTables = ['business_settings', 'banners', 'product_categories', 'products'];
        $missingTables = array_values(array_filter(
            $requiredTables,
            fn (string $table): bool => ! Schema::hasTable($table)
        ));
    } catch (QueryException $exception) {
        report($exception);

        $databaseHost = (string) DB::connection()->getConfig('host');
        $hostType = match (true) {
            str_contains($databaseHost, '${{') => 'unresolved_reference',
            in_array($databaseHost, ['127.0.0.1', 'localhost', '::1'], true) => 'loopback',
            str_ends_with($databaseHost, '.railway.internal') => 'railway_private',
            default => 'other',
        };

        return response()->json([
            'success' => false,
            'message' => 'Database connection is unavailable.',
            'errors' => (object) [],
            'meta' => [
                'sqlstate' => $exception->errorInfo[0] ?? null,
                'driver_code' => $exception->errorInfo[1] ?? null,
                'host_type' => $hostType,
                'port' => (int) DB::connection()->getConfig('port'),
            ],
        ], 503);
    }

    if ($missingTables !== []) {
        return response()->json([
            'success' => false,
            'message' => 'Database schema is incomplete.',
            'errors' => (object) [],
            'meta' => ['missing_tables' => $missingTables],
        ], 503);
    }

    return response()->json([
        'success' => true,
        'message' => 'Database is ready.',
        'data' => ['connection' => 'mysql', 'schema' => 'ready'],
        'meta' => (object) [],
    ]);
});

Route::get('/v1/app/bootstrap', [CustomerCatalogController::class, 'getAppBootstrap']);
Route::get('/v1/home/get-home-data', [CustomerCatalogController::class, 'getHomeData']);
Route::get('/v1/categories/get-category-list', [CustomerCatalogController::class, 'getCategoryList']);
Route::get('/v1/products/get-product-list', [CustomerCatalogController::class, 'getProductList']);
Route::get('/v1/products/get-product-detail', [CustomerCatalogController::class, 'getProductDetail']);
Route::get('/v1/products/get-product-order-options', [CustomerCheckoutController::class, 'getProductOrderOptions']);
Route::post('/v1/location/search-area', [CustomerLocationController::class, 'searchDeliveryArea']);
Route::post('/v1/location/check-delivery-availability', [CustomerDeliveryPricingController::class, 'checkDeliveryAvailability']);
Route::post('/v1/auth/send-otp', [CustomerIdentityController::class, 'sendOtp']);
Route::post('/v1/auth/verify-otp', [CustomerIdentityController::class, 'verifyOtp']);
Route::post('/v1/admin/auth/login-admin', [AdminAuthController::class, 'loginAdmin']);

Route::prefix('v1/mobile')->group(function () {
    Route::get('/app/bootstrap', [CustomerCatalogController::class, 'getAppBootstrap']);
    Route::get('/home/get-home-data', [CustomerCatalogController::class, 'getHomeData']);
    Route::get('/categories/get-category-list', [CustomerCatalogController::class, 'getCategoryList']);
    Route::get('/offers/get-offer-list', [CustomerCatalogController::class, 'getMobileOfferList']);
    Route::get('/products/get-product-list', [CustomerCatalogController::class, 'getProductList']);
    Route::get('/products/get-product-detail', [CustomerCatalogController::class, 'getProductDetail']);
    Route::get('/products/get-product-order-options', [CustomerCheckoutController::class, 'getProductOrderOptions']);
    Route::post('/location/search-area', [CustomerLocationController::class, 'searchDeliveryArea']);
    Route::post('/location/check-delivery-availability', [CustomerDeliveryPricingController::class, 'checkDeliveryAvailability']);
    Route::post('/auth/send-otp', [CustomerIdentityController::class, 'sendMobileOtp']);
    Route::post('/auth/verify-otp', [CustomerIdentityController::class, 'verifyMobileOtp']);

    Route::middleware(['auth:sanctum', 'customer.role'])->group(function () {
        Route::middleware('ability:mobile-customer-access,mobile:read')->group(function () {
            Route::get('/customer/get-profile', [CustomerIdentityController::class, 'getProfile']);
            Route::get('/customer/addresses/get-address-list', [CustomerAddressController::class, 'getAddressList']);
            Route::get('/cart/get-cart-detail', [CustomerCartController::class, 'getCartDetail']);
            Route::get('/checkout/get-available-schedules', [CustomerCheckoutController::class, 'getAvailableSchedules']);
            Route::get('/orders/get-order-list', [CustomerOrderController::class, 'getOrderList']);
            Route::get('/orders/get-order-detail', [CustomerOrderController::class, 'getOrderDetail']);
            Route::get('/favorites/get-favorite-list', [CustomerEngagementController::class, 'getFavoriteList']);
            Route::get('/support/get-ticket-list', [CustomerSupportController::class, 'getTicketList']);
            Route::get('/support/get-ticket-detail', [CustomerSupportController::class, 'getTicketDetail']);
            Route::get('/support/get-attachment', [CustomerSupportController::class, 'getAttachment']);
        });

        Route::middleware('ability:mobile-customer-access,mobile:write')->group(function () {
            Route::post('/auth/logout', [CustomerIdentityController::class, 'logoutCustomer']);
            Route::post('/customer/update-profile', [CustomerIdentityController::class, 'updateProfile']);
            Route::post('/customer/addresses/create-address', [CustomerAddressController::class, 'createAddress']);
            Route::post('/customer/addresses/update-address', [CustomerAddressController::class, 'updateAddress']);
            Route::post('/customer/addresses/delete-address', [CustomerAddressController::class, 'deleteAddress']);
            Route::post('/cart/add-cart-item', [CustomerCartController::class, 'addCartItem']);
            Route::post('/cart/update-cart-item', [CustomerCartController::class, 'updateCartItem']);
            Route::post('/cart/remove-cart-item', [CustomerCartController::class, 'removeCartItem']);
            Route::post('/cart/clear-cart', [CustomerCartController::class, 'clearCart']);
            Route::post('/cart/add-configured-product', [CustomerCheckoutController::class, 'addConfiguredProductToCart']);
            Route::post('/checkout/validate-checkout', [CustomerOrderController::class, 'validateCheckout']);
            Route::post('/checkout/get-checkout-summary', [CustomerCheckoutController::class, 'getCheckoutSummary']);
            Route::post('/orders/place-customer-order', [CustomerCheckoutController::class, 'placeCustomerOrder']);
            Route::post('/orders/cancel-order', [CustomerOrderController::class, 'cancelOrder']);
            Route::post('/orders/reorder-order', [CustomerOrderController::class, 'reorderOrder']);
            Route::post('/coupons/apply-coupon', [CustomerEngagementController::class, 'applyCoupon']);
            Route::post('/favorites/update-favorite', [CustomerEngagementController::class, 'updateFavorite']);
            Route::post('/support/create-ticket', [CustomerSupportController::class, 'createSupportTicket']);
            Route::post('/support/reply-ticket', [CustomerSupportController::class, 'replyTicket']);
            Route::post('/support/mark-messages-read', [CustomerSupportController::class, 'markMessagesRead']);
            Route::post('/support/send-typing-indicator', [CustomerSupportController::class, 'sendTypingIndicator']);
        });
    });
});

Route::middleware(['auth:sanctum', 'ability:customer-access', 'customer.role'])->group(function () {
    Route::post('/v1/auth/logout', [CustomerIdentityController::class, 'logoutCustomer']);
    Route::get('/v1/customer/get-profile', [CustomerIdentityController::class, 'getProfile']);
    Route::post('/v1/customer/update-profile', [CustomerIdentityController::class, 'updateProfile']);
    Route::get('/v1/customer/addresses/get-address-list', [CustomerAddressController::class, 'getAddressList']);
    Route::post('/v1/customer/addresses/create-address', [CustomerAddressController::class, 'createAddress']);
    Route::post('/v1/customer/addresses/update-address', [CustomerAddressController::class, 'updateAddress']);
    Route::post('/v1/customer/addresses/delete-address', [CustomerAddressController::class, 'deleteAddress']);
    Route::get('/v1/cart/get-cart-detail', [CustomerCartController::class, 'getCartDetail']);
    Route::post('/v1/cart/add-cart-item', [CustomerCartController::class, 'addCartItem']);
    Route::post('/v1/cart/update-cart-item', [CustomerCartController::class, 'updateCartItem']);
    Route::post('/v1/cart/remove-cart-item', [CustomerCartController::class, 'removeCartItem']);
    Route::post('/v1/cart/clear-cart', [CustomerCartController::class, 'clearCart']);
    Route::post('/v1/checkout/validate-checkout', [CustomerOrderController::class, 'validateCheckout']);
    Route::post('/v1/cart/add-configured-product', [CustomerCheckoutController::class, 'addConfiguredProductToCart']);
    Route::get('/v1/checkout/get-available-schedules', [CustomerCheckoutController::class, 'getAvailableSchedules']);
    Route::post('/v1/checkout/get-checkout-summary', [CustomerCheckoutController::class, 'getCheckoutSummary']);
    Route::post('/v1/orders/place-customer-order', [CustomerCheckoutController::class, 'placeCustomerOrder']);
    Route::post('/v1/orders/place-order', [CustomerCheckoutController::class, 'placeCustomerOrder']);
    Route::get('/v1/orders/get-order-list', [CustomerOrderController::class, 'getOrderList']);
    Route::get('/v1/orders/get-order-detail', [CustomerOrderController::class, 'getOrderDetail']);
    Route::post('/v1/orders/cancel-order', [CustomerOrderController::class, 'cancelOrder']);
    Route::post('/v1/orders/reorder-order', [CustomerOrderController::class, 'reorderOrder']);
    Route::post('/v1/coupons/apply-coupon', [CustomerEngagementController::class, 'applyCoupon']);
    Route::get('/v1/favorites/get-favorite-list', [CustomerEngagementController::class, 'getFavoriteList']);
    Route::post('/v1/favorites/update-favorite', [CustomerEngagementController::class, 'updateFavorite']);
    Route::get('/v1/support/get-ticket-list', [CustomerSupportController::class, 'getTicketList']);
    Route::get('/v1/support/get-ticket-detail', [CustomerSupportController::class, 'getTicketDetail']);
    Route::post('/v1/support/create-ticket', [CustomerSupportController::class, 'createSupportTicket']);
    Route::post('/v1/support/reply-ticket', [CustomerSupportController::class, 'replyTicket']);
    Route::post('/v1/support/mark-messages-read', [CustomerSupportController::class, 'markMessagesRead']);
    Route::post('/v1/support/send-typing-indicator', [CustomerSupportController::class, 'sendTypingIndicator']);
});

// The attachment URL is shared by customer and admin message payloads. The
// request and chat service authorize access to the parent conversation.
Route::middleware('auth:sanctum')->get(
    '/v1/support/get-attachment',
    [CustomerSupportController::class, 'getAttachment']
);

Route::middleware(['auth:sanctum', 'ability:admin-access', 'admin.role:owner,admin,staff'])->prefix('v1/admin/support')->group(function () {
    Route::get('/get-ticket-list', [AdminSupportController::class, 'getTicketList']);
    Route::get('/get-ticket-detail', [AdminSupportController::class, 'getTicketDetail']);
    Route::get('/get-attachment', [AdminSupportController::class, 'getAttachment']);
    Route::post('/reply-ticket', [AdminSupportController::class, 'replyTicket']);
    Route::post('/mark-messages-read', [AdminSupportController::class, 'markMessagesRead']);
    Route::post('/send-typing-indicator', [AdminSupportController::class, 'sendTypingIndicator']);
    Route::post('/update-ticket-status', [AdminSupportController::class, 'updateTicketStatus']);
});

Route::middleware(['auth:sanctum', 'ability:admin-access', 'admin.role:owner,admin,staff'])->prefix('v1/admin')->group(function () {
    Route::post('/auth/logout-admin', [AdminAuthController::class, 'logoutAdmin']);
    Route::get('/dashboard/get-dashboard-summary', [AdminDashboardController::class, 'getDashboardSummary']);

    Route::get('/orders/get-order-list', [AdminOrderController::class, 'getOrderList']);
    Route::get('/orders/get-dispatcher-orders', [AdminOrderController::class, 'getDispatcherOrders']);
    Route::get('/orders/export-order-list', [AdminOrderController::class, 'exportOrderList']);
    Route::get('/orders/get-order-detail', [AdminOrderController::class, 'getOrderDetail']);
    Route::post('/orders/update-order-status', [AdminOrderController::class, 'updateOrderStatus']);
    Route::post('/orders/accept-order', [AdminOrderController::class, 'acceptOrder']);
    Route::post('/orders/mark-order-as-preparing', [AdminOrderController::class, 'markOrderAsPreparing']);
    Route::post('/orders/mark-order-as-ready', [AdminOrderController::class, 'markOrderAsReady']);
    Route::post('/orders/mark-order-as-out-for-delivery', [AdminOrderController::class, 'markOrderAsOutForDelivery']);
    Route::post('/orders/mark-order-as-delivered', [AdminOrderController::class, 'markOrderAsDelivered']);
    Route::post('/orders/mark-order-as-picked-up', [AdminOrderController::class, 'markOrderAsPickedUp']);
    Route::post('/orders/cancel-order', [AdminOrderController::class, 'cancelOrder']);
    Route::post('/orders/update-order-internal-note', [AdminOrderController::class, 'updateOrderInternalNote']);

    Route::get('/categories/get-category-list', [AdminCatalogController::class, 'getCategoryList']);
    Route::post('/categories/create-category', [AdminCatalogController::class, 'createCategory']);
    Route::post('/categories/update-category', [AdminCatalogController::class, 'updateCategory']);
    Route::post('/categories/update-category-status', [AdminCatalogController::class, 'updateCategoryStatus']);
    Route::post('/categories/delete-category', [AdminCatalogController::class, 'deleteCategory']);
    Route::get('/products/get-product-list', [AdminCatalogController::class, 'getProductList']);
    Route::post('/products/create-product', [AdminCatalogController::class, 'createProduct']);
    Route::post('/products/update-product', [AdminCatalogController::class, 'updateProduct']);
    Route::post('/products/update-product-category', [AdminCatalogController::class, 'updateProductCategory']);
    Route::post('/products/update-product-status', [AdminCatalogController::class, 'updateProductStatus']);
    Route::post('/products/update-product-stock', [AdminCatalogController::class, 'updateProductStock']);
    Route::post('/products/delete-product', [AdminCatalogController::class, 'deleteProduct']);
    Route::post('/products/upload-product-image', [AdminCatalogController::class, 'uploadProductImage']);
    Route::post('/products/delete-product-image', [AdminCatalogController::class, 'deleteProductImage']);
    Route::post('/products/update-product-image-order', [AdminCatalogController::class, 'updateProductImageOrder']);
    Route::get('/products/get-flavour-list', [AdminCatalogController::class, 'getFlavourList']);
    Route::post('/products/save-flavour', [AdminCatalogController::class, 'saveFlavour']);
    Route::get('/products/get-addon-list', [AdminCatalogController::class, 'getAddonList']);
    Route::post('/products/save-addon', [AdminCatalogController::class, 'saveAddon']);
});

Route::middleware(['auth:sanctum', 'ability:admin-access', 'admin.role:owner,admin'])->prefix('v1/admin')->group(function () {
    Route::get('/delivery/get-fee-range-list', [AdminDeliveryPricingController::class, 'getDeliveryFeeRanges']);
    Route::post('/delivery/create-fee-range', [AdminDeliveryPricingController::class, 'createDeliveryFeeRange']);
    Route::post('/delivery/update-fee-range', [AdminDeliveryPricingController::class, 'updateDeliveryFeeRange']);
    Route::post('/delivery/update-fee-range-status', [AdminDeliveryPricingController::class, 'updateDeliveryFeeRangeStatus']);
    Route::post('/delivery/delete-fee-range', [AdminDeliveryPricingController::class, 'deleteDeliveryFeeRange']);
    Route::get('/content/get-banner-list', [AdminContentController::class, 'getBannerList']);
    Route::post('/content/create-banner', [AdminContentController::class, 'createBanner']);
    Route::post('/content/update-banner', [AdminContentController::class, 'updateBanner']);
    Route::post('/content/update-banner-status', [AdminContentController::class, 'updateBannerStatus']);
    Route::post('/content/delete-banner', [AdminContentController::class, 'deleteBanner']);
    Route::get('/settings/get-business-settings', [AdminBusinessController::class, 'getBusinessSettings']);
    Route::post('/settings/update-business-settings', [AdminBusinessController::class, 'updateBusinessSettings']);
    Route::get('/delivery/get-opening-hours', [AdminBusinessController::class, 'getOpeningHours']);
    Route::post('/delivery/update-opening-hours', [AdminBusinessController::class, 'updateOpeningHours']);
    Route::get('/delivery/get-zone-list', [AdminBusinessController::class, 'getDeliveryZones']);
    Route::post('/delivery/create-zone', [AdminBusinessController::class, 'createDeliveryZone']);
    Route::post('/delivery/update-zone', [AdminBusinessController::class, 'updateDeliveryZone']);
    Route::post('/delivery/update-zone-status', [AdminBusinessController::class, 'updateDeliveryZoneStatus']);
    Route::post('/delivery/delete-zone', [AdminBusinessController::class, 'deleteDeliveryZone']);
    Route::get('/delivery/get-holiday-list', [AdminBusinessController::class, 'getHolidayList']);
    Route::post('/delivery/create-holiday', [AdminBusinessController::class, 'createHoliday']);
    Route::post('/delivery/delete-holiday', [AdminBusinessController::class, 'deleteHoliday']);
    Route::get('/delivery/get-delivery-slots', [AdminBusinessController::class, 'getDeliverySlots']);
    Route::post('/delivery/save-delivery-slot', [AdminBusinessController::class, 'saveDeliverySlot']);

    Route::get('/coupons/get-coupon-list', [AdminOperationsController::class, 'getCouponList']);
    Route::post('/coupons/create-coupon', [AdminOperationsController::class, 'createCoupon']);
    Route::post('/coupons/update-coupon', [AdminOperationsController::class, 'updateCoupon']);
    Route::post('/coupons/update-coupon-status', [AdminOperationsController::class, 'updateCouponStatus']);
    Route::post('/coupons/delete-coupon', [AdminOperationsController::class, 'deleteCoupon']);
    Route::get('/staff/get-staff-list', [AdminOperationsController::class, 'getStaffList']);
    Route::post('/staff/create-staff', [AdminOperationsController::class, 'createStaff']);
    Route::post('/staff/update-staff', [AdminOperationsController::class, 'updateStaff']);
    Route::post('/staff/update-staff-status', [AdminOperationsController::class, 'updateStaffStatus']);
    Route::get('/staff/get-activity-log', [AdminOperationsController::class, 'getActivityLog']);
    Route::get('/customers/get-customer-list', [AdminOperationsController::class, 'getCustomerList']);
    Route::post('/customers/update-customer-status', [AdminOperationsController::class, 'updateCustomerStatus']);
});
