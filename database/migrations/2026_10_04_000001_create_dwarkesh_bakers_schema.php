<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('mobile_number')->unique();
            $table->string('email')->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->string('role')->index();
            $table->string('avatar_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('customer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('full_name')->nullable();
            $table->string('mobile_number')->index();
            $table->string('email')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->date('anniversary_date')->nullable();
            $table->string('preferred_language', 5)->default('gu');
            $table->boolean('marketing_opt_in')->default(false);
            $table->timestamps();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('business_settings', function (Blueprint $table) {
            $table->id();
            $table->string('bakery_name');
            $table->string('logo_path')->nullable();
            $table->string('cover_image_path')->nullable();
            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->string('area');
            $table->string('city')->default('Jamnagar');
            $table->string('state')->default('Gujarat');
            $table->string('pincode', 20);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('phone_number');
            $table->string('whatsapp_number');
            $table->string('email')->nullable();
            $table->string('gst_number')->nullable();
            $table->string('fssai_number')->nullable();
            $table->decimal('minimum_order_amount', 10, 2)->default(0);
            $table->unsignedInteger('default_preparation_minutes')->default(60);
            $table->boolean('delivery_available')->default(true);
            $table->boolean('takeaway_available')->default(true);
            $table->boolean('online_payment_available')->default(true);
            $table->boolean('cod_available')->default(false);
            $table->boolean('upi_available')->default(true);
            $table->boolean('maintenance_mode')->default(false);
            $table->text('maintenance_message')->nullable();
            $table->json('social_links_json')->nullable();
            $table->timestamps();
        });

        Schema::create('opening_hours', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('day_of_week')->unique();
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->timestamps();
        });

        Schema::create('business_holidays', function (Blueprint $table) {
            $table->id();
            $table->date('holiday_date')->index();
            $table->string('title');
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->boolean('is_closed')->default(true);
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('area_names_json')->nullable();
            $table->json('pincodes_json')->nullable();
            $table->decimal('center_latitude', 10, 7)->nullable();
            $table->decimal('center_longitude', 10, 7)->nullable();
            $table->decimal('radius_km', 8, 2)->nullable();
            $table->decimal('minimum_order_amount', 10, 2)->nullable();
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('free_delivery_above', 10, 2)->nullable();
            $table->unsignedInteger('estimated_delivery_minutes')->default(45);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('delivery_slots', function (Blueprint $table) {
            $table->id();
            $table->string('fulfillment_type')->index();
            $table->string('label');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->unsignedInteger('max_orders')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->json('name_translations');
            $table->string('slug')->unique();
            $table->json('description_translations')->nullable();
            $table->string('image_path')->nullable();
            $table->string('banner_image_path')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_category_id')->constrained('product_categories')->restrictOnDelete();
            $table->json('name_translations');
            $table->string('slug')->unique();
            $table->json('short_description_translations')->nullable();
            $table->json('description_translations')->nullable();
            $table->string('main_image_path')->nullable();
            $table->decimal('base_price', 10, 2);
            $table->boolean('price_starts_from')->default(true);
            $table->string('sku')->nullable()->unique();
            $table->string('product_type')->index();
            $table->string('food_type')->index();
            $table->boolean('is_eggless_available')->default(false);
            $table->boolean('is_egg_available')->default(false);
            $table->boolean('is_customizable')->default(false);
            $table->boolean('allow_message')->default(false);
            $table->boolean('allow_photo_upload')->default(false);
            $table->unsignedInteger('min_preparation_minutes')->default(60);
            $table->unsignedInteger('max_preparation_minutes')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_bestseller')->default(false);
            $table->boolean('is_new')->default(false);
            $table->boolean('is_available')->default(true);
            $table->timestamp('sold_out_until')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('image_path');
            $table->json('alt_text_translations')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->json('name_translations');
            $table->string('sku')->nullable()->unique();
            $table->decimal('weight_value', 8, 2)->nullable();
            $table->string('weight_unit')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('compare_at_price', 10, 2)->nullable();
            $table->unsignedInteger('min_preparation_minutes')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('product_flavours', function (Blueprint $table) {
            $table->id();
            $table->json('name_translations');
            $table->json('description_translations')->nullable();
            $table->decimal('extra_price', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('product_flavour_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_flavour_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->unique(['product_id', 'product_flavour_id']);
        });

        Schema::create('product_addons', function (Blueprint $table) {
            $table->id();
            $table->json('name_translations');
            $table->json('description_translations')->nullable();
            $table->string('image_path')->nullable();
            $table->decimal('price', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('product_addon_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_addon_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('max_quantity')->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'product_addon_id']);
        });

        Schema::create('product_customization_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('allow_message')->default(false);
            $table->json('message_label_translations')->nullable();
            $table->unsignedInteger('message_max_length')->nullable();
            $table->boolean('allow_photo_upload')->default(false);
            $table->json('photo_upload_label_translations')->nullable();
            $table->boolean('allow_flavour_selection')->default(false);
            $table->boolean('allow_egg_selection')->default(false);
            $table->boolean('allow_delivery_date_selection')->default(true);
            $table->unsignedInteger('min_notice_minutes')->nullable();
            $table->unsignedInteger('max_schedule_days')->nullable();
            $table->timestamps();
        });

        Schema::create('product_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->boolean('track_stock')->default(false);
            $table->integer('stock_quantity')->nullable();
            $table->unsignedInteger('low_stock_threshold')->nullable();
            $table->boolean('is_sold_out')->default(false);
            $table->timestamp('sold_out_until')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_otps', function (Blueprint $table) {
            $table->id();
            $table->string('mobile_number')->index();
            $table->string('otp_hash');
            $table->string('purpose')->default('login');
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('ip_address')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('receiver_name');
            $table->string('receiver_mobile_number');
            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->string('landmark')->nullable();
            $table->string('area');
            $table->string('city')->default('Jamnagar');
            $table->string('state')->default('Gujarat');
            $table->string('pincode', 20);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->foreignId('delivery_zone_id')->nullable()->constrained('delivery_zones')->nullOnDelete();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('customer_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'product_id']);
        });

        Schema::create('customer_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            $table->string('type')->index();
            $table->json('data_json')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->json('title_translations');
            $table->json('description_translations')->nullable();
            $table->string('discount_type');
            $table->decimal('discount_value', 10, 2);
            $table->decimal('max_discount_amount', 10, 2)->nullable();
            $table->decimal('minimum_order_amount', 10, 2)->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_limit_per_customer')->nullable();
            $table->string('applies_to_fulfillment_type')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('guest_token')->nullable()->index();
            $table->string('fulfillment_type')->nullable();
            $table->foreignId('delivery_zone_id')->nullable()->constrained('delivery_zones')->nullOnDelete();
            $table->foreignId('customer_address_id')->nullable()->constrained('customer_addresses')->nullOnDelete();
            $table->timestamp('scheduled_for')->nullable();
            $table->foreignId('coupon_id')->nullable()->constrained('coupons')->nullOnDelete();
            $table->string('currency', 3)->default('INR');
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('addon_total', 10, 2)->default(0);
            $table->decimal('discount_total', 10, 2)->default(0);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('tax_total', 10, 2)->default(0);
            $table->decimal('grand_total', 10, 2)->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('addon_total', 10, 2)->default(0);
            $table->decimal('line_total', 10, 2);
            $table->foreignId('selected_flavour_id')->nullable()->constrained('product_flavours')->nullOnDelete();
            $table->string('egg_option')->nullable();
            $table->string('cake_message')->nullable();
            $table->json('customization_summary_json')->nullable();
            $table->timestamps();
        });

        Schema::create('cart_item_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_addon_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('line_total', 10, 2);
            $table->timestamps();
        });

        Schema::create('cart_item_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_item_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size');
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_mobile_number');
            $table->string('customer_email')->nullable();
            $table->string('fulfillment_type')->index();
            $table->string('order_status')->index();
            $table->string('payment_status')->index();
            $table->string('payment_method')->index();
            $table->foreignId('delivery_zone_id')->nullable()->constrained('delivery_zones')->nullOnDelete();
            $table->json('delivery_address_json')->nullable();
            $table->json('pickup_address_json')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('placed_at')->useCurrent();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('prepared_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('out_for_delivery_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('addon_total', 10, 2)->default(0);
            $table->decimal('discount_total', 10, 2)->default(0);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('tax_total', 10, 2)->default(0);
            $table->decimal('grand_total', 10, 2)->default(0);
            $table->text('customer_note')->nullable();
            $table->text('internal_note')->nullable();
            $table->foreignId('coupon_id')->nullable()->constrained('coupons')->nullOnDelete();
            $table->string('coupon_code')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->decimal('discount_amount', 10, 2);
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('product_name');
            $table->string('variant_name')->nullable();
            $table->string('sku')->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('addon_total', 10, 2)->default(0);
            $table->decimal('line_total', 10, 2);
            $table->string('selected_flavour_name')->nullable();
            $table->string('egg_option')->nullable();
            $table->string('cake_message')->nullable();
            $table->json('customization_summary_json')->nullable();
            $table->timestamps();
        });

        Schema::create('order_item_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_addon_id')->nullable()->constrained('product_addons')->nullOnDelete();
            $table->string('addon_name');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('line_total', 10, 2);
            $table->timestamps();
        });

        Schema::create('order_item_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size');
            $table->timestamps();
        });

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('old_status')->nullable();
            $table->string('new_status');
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->boolean('visible_to_customer')->default(true);
            $table->timestamps();
        });

        Schema::create('delivery_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delivery_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('status')->index();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('delivery_tracking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delivery_assignment_id')->nullable()->constrained('delivery_assignments')->cascadeOnDelete();
            $table->string('status')->index();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('note')->nullable();
            $table->boolean('visible_to_customer')->default(true);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->index();
            $table->string('provider_order_id')->nullable()->index();
            $table->string('provider_payment_id')->nullable()->index();
            $table->string('provider_signature')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('INR');
            $table->string('status')->index();
            $table->timestamp('paid_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('raw_response_json')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->index();
            $table->string('event_type')->nullable();
            $table->string('provider_payment_id')->nullable()->index();
            $table->json('payload_json');
            $table->timestamp('processed_at')->nullable();
            $table->text('processing_error')->nullable();
            $table->timestamps();
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->text('reason');
            $table->string('status')->index();
            $table->string('provider_refund_id')->nullable();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('processed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->json('raw_response_json')->nullable();
            $table->timestamps();
        });

        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->string('title')->nullable();
            $table->text('comment')->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('is_approved')->default(false);
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject');
            $table->string('status')->index();
            $table->string('priority')->default('normal')->index();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sender_type')->index();
            $table->text('message');
            $table->string('attachment_path')->nullable();
            $table->timestamps();
        });

        Schema::create('admin_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action')->index();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('properties_json')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->json('title_translations');
            $table->json('subtitle_translations')->nullable();
            $table->string('image_path');
            $table->string('link_type')->nullable();
            $table->string('link_value')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'banners',
            'admin_activity_logs',
            'support_messages',
            'support_tickets',
            'product_reviews',
            'refunds',
            'payment_webhook_logs',
            'payments',
            'delivery_tracking_events',
            'delivery_assignments',
            'order_status_histories',
            'order_item_uploads',
            'order_item_addons',
            'order_items',
            'coupon_redemptions',
            'orders',
            'cart_item_uploads',
            'cart_item_addons',
            'cart_items',
            'carts',
            'coupons',
            'customer_notifications',
            'customer_favorites',
            'customer_addresses',
            'customer_otps',
            'product_inventory',
            'product_customization_options',
            'product_addon_options',
            'product_addons',
            'product_flavour_options',
            'product_flavours',
            'product_variants',
            'product_images',
            'products',
            'product_categories',
            'delivery_slots',
            'delivery_zones',
            'business_holidays',
            'opening_hours',
            'business_settings',
            'personal_access_tokens',
            'customer_profiles',
            'users',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
