<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('business_settings', 'max_delivery_distance_km')) {
            Schema::table('business_settings', function (Blueprint $table): void {
                $table->decimal('max_delivery_distance_km', 7, 2)
                    ->default(10)
                    ->after('minimum_order_amount');
            });
        }

        if (! Schema::hasTable('delivery_fee_ranges')) {
            Schema::create('delivery_fee_ranges', function (Blueprint $table): void {
                $table->id();
                $table->decimal('minimum_distance_km', 7, 2)->default(0);
                $table->decimal('maximum_distance_km', 7, 2);
                $table->decimal('delivery_fee', 10, 2)->default(0);
                $table->unsignedSmallInteger('estimated_delivery_minutes')->default(45);
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->index(
                    ['minimum_distance_km', 'maximum_distance_km'],
                    'delivery_fee_ranges_distance_idx'
                );
            });
        }

        if (DB::table('delivery_fee_ranges')->doesntExist()) {
            $now = now();
            DB::table('delivery_fee_ranges')->insert([
                [
                    'minimum_distance_km' => 0,
                    'maximum_distance_km' => 3,
                    'delivery_fee' => 40,
                    'estimated_delivery_minutes' => 35,
                    'is_active' => true,
                    'sort_order' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'minimum_distance_km' => 3,
                    'maximum_distance_km' => 5,
                    'delivery_fee' => 60,
                    'estimated_delivery_minutes' => 45,
                    'is_active' => true,
                    'sort_order' => 2,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'minimum_distance_km' => 5,
                    'maximum_distance_km' => 10,
                    'delivery_fee' => 90,
                    'estimated_delivery_minutes' => 60,
                    'is_active' => true,
                    'sort_order' => 3,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_fee_ranges');

        if (Schema::hasColumn('business_settings', 'max_delivery_distance_km')) {
            Schema::table('business_settings', function (Blueprint $table): void {
                $table->dropColumn('max_delivery_distance_km');
            });
        }
    }
};
