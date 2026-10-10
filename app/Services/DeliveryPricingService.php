<?php

namespace App\Services;

use App\Models\BusinessSetting;
use App\Models\DeliveryFeeRange;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Resolve delivery from the bakery pin using unrounded great-circle distance. */
class DeliveryPricingService
{
    /** Quote a validated customer map point; reject disabled, uncovered or out-of-radius delivery. */
    public function quote(float $latitude, float $longitude): array
    {
        if (! is_finite($latitude) || ! is_finite($longitude)
            || abs($latitude) > 90 || abs($longitude) > 180) {
            throw ValidationException::withMessages(['delivery_address' => ['Choose a valid map location.']]);
        }

        $query = BusinessSetting::query()->orderBy('id');
        if (DB::transactionLevel() > 0) {
            $query->lockForUpdate();
        }
        $business = $query->first();
        if (! $business?->delivery_available || $business->latitude === null || $business->longitude === null
            || abs((float) $business->latitude) > 90 || abs((float) $business->longitude) > 180) {
            throw ValidationException::withMessages(['delivery_address' => ['Delivery is currently unavailable.']]);
        }

        $distance = $this->distanceInKilometres(
            (float) $business->latitude,
            (float) $business->longitude,
            $latitude,
            $longitude
        );
        $maximum = (float) $business->max_delivery_distance_km;
        if ($maximum <= 0 || $distance > $maximum) {
            throw ValidationException::withMessages([
                'delivery_address' => ['This location is outside our delivery radius. Choose takeaway or another address.'],
            ]);
        }

        // Shared endpoints belong to the preceding range: [0, 3], (3, 5], (5, 10].
        $range = DeliveryFeeRange::query()
            ->where('is_active', true)
            ->where('maximum_distance_km', '>=', $distance)
            ->where(function ($query) use ($distance): void {
                $query->where('minimum_distance_km', '<', $distance);
                if ($distance === 0.0) {
                    $query->orWhere('minimum_distance_km', 0);
                }
            })
            ->orderBy('maximum_distance_km')
            ->first();
        if (! $range) {
            throw ValidationException::withMessages([
                'delivery_address' => ['Delivery pricing is unavailable for this distance. Choose takeaway or another address.'],
            ]);
        }

        return [
            'is_available' => true,
            'distance_km' => round($distance, 3),
            'max_delivery_distance_km' => $maximum,
            'delivery_fee' => (float) $range->delivery_fee,
            'estimated_delivery_minutes' => (int) $range->estimated_delivery_minutes,
            'minimum_order_amount' => (float) $business->minimum_order_amount,
            'distance_basis' => 'great_circle',
        ];
    }

    /** Calculate a stable spherical distance without rounding before eligibility or pricing. */
    public function distanceInKilometres(float $originLatitude, float $originLongitude, float $latitude, float $longitude): float
    {
        $latitudeDelta = deg2rad($latitude - $originLatitude);
        $longitudeDelta = deg2rad($longitude - $originLongitude);
        $haversine = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($originLatitude)) * cos(deg2rad($latitude)) * sin($longitudeDelta / 2) ** 2;

        return 6371.0088 * 2 * asin(sqrt(max(0, min(1, $haversine))));
    }
}
