<?php

namespace App\Services\Admin;

use App\Models\BusinessSetting;
use App\Models\DeliveryFeeRange;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Serialize distance pricing writes with the bakery row to prevent overlapping active ranges. */
class AdminDeliveryPricingService
{
    /** Create or update a range while rejecting ambiguous active intervals. */
    public function saveRange(array $data, ?int $id = null): DeliveryFeeRange
    {
        return DB::transaction(function () use ($data, $id): DeliveryFeeRange {
            $this->lockBusiness();
            $range = $id ? DeliveryFeeRange::query()->findOrFail($id) : new DeliveryFeeRange();
            unset($data['id']);
            $range->fill($data);
            $this->assertNoOverlap($range);
            $range->save();

            return $range;
        });
    }

    /** Activate or pause a range, checking overlaps before activation. */
    public function changeStatus(int $id, bool $active): DeliveryFeeRange
    {
        return $this->saveRange(['is_active' => $active], $id);
    }

    /** Delete a pricing rule; historical orders retain their captured monetary fee. */
    public function deleteRange(int $id): DeliveryFeeRange
    {
        return DB::transaction(function () use ($id): DeliveryFeeRange {
            $this->lockBusiness();
            $range = DeliveryFeeRange::query()->findOrFail($id);
            $range->delete();

            return $range;
        });
    }

    private function lockBusiness(): void
    {
        if (! BusinessSetting::query()->orderBy('id')->lockForUpdate()->first()) {
            throw ValidationException::withMessages(['settings' => ['Save the bakery settings before configuring delivery pricing.']]);
        }
    }

    private function assertNoOverlap(DeliveryFeeRange $range): void
    {
        if (! $range->is_active) {
            return;
        }
        $query = DeliveryFeeRange::query()
            ->where('is_active', true)
            ->where('minimum_distance_km', '<', $range->maximum_distance_km)
            ->where('maximum_distance_km', '>', $range->minimum_distance_km);
        if ($range->exists) {
            $query->whereKeyNot($range->id);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages([
                'minimum_distance_km' => ['Active distance ranges cannot overlap. Adjacent ranges may share an endpoint.'],
            ]);
        }
    }
}
