<?php

namespace App\Services;

use App\Models\BusinessSetting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Resolve current online ordering availability from live bakery settings and hours. */
class CustomerStoreAvailability
{
    private const TIMEZONE = 'Asia/Kolkata';

    /** Return current opening state and a customer-friendly explanation. */
    public function current(?BusinessSetting $business): array
    {
        $now = CarbonImmutable::now(self::TIMEZONE);
        $hours = DB::table('opening_hours')
            ->where('day_of_week', $now->dayOfWeek)
            ->first();
        $holiday = DB::table('business_holidays')
            ->whereDate('holiday_date', $now->toDateString())
            ->first();
        $isTakingOrders = $business !== null
            && ! $business->maintenance_mode
            && ($business->delivery_available || $business->takeaway_available);

        if (! $isTakingOrders) {
            return [
                'is_open' => false,
                'message' => $business?->maintenance_mode && $business->maintenance_message
                    ? $business->maintenance_message
                    : 'The bakery is not accepting online orders right now.',
                'today_hours' => null,
            ];
        }

        $isClosed = $holiday?->is_closed ?? $hours?->is_closed ?? false;
        $opensAt = $holiday?->opens_at ?? $hours?->opens_at;
        $closesAt = $holiday?->closes_at ?? $hours?->closes_at;

        if ($isClosed) {
            return [
                'is_open' => false,
                'message' => 'The bakery is closed today. Please contact us and we’ll be happy to help.',
                'today_hours' => null,
            ];
        }

        if (! $opensAt || ! $closesAt) {
            return [
                'is_open' => true,
                'message' => 'Online ordering is available.',
                'today_hours' => null,
            ];
        }

        $opens = CarbonImmutable::parse($now->toDateString().' '.$opensAt, self::TIMEZONE);
        $closes = CarbonImmutable::parse($now->toDateString().' '.$closesAt, self::TIMEZONE);

        if ($closes->lessThanOrEqualTo($opens)) {
            $closes = $closes->addDay();
        }

        $isOpen = $now->greaterThanOrEqualTo($opens) && $now->lessThan($closes);
        $message = $isOpen
            ? 'The bakery is open and ready for your order.'
            : ($now->lessThan($opens)
                ? 'We open today at '.$opens->format('g:i A').'.'
                : 'We are closed for today. Please contact us and we’ll be happy to help.');

        return [
            'is_open' => $isOpen,
            'message' => $message,
            'today_hours' => $opens->format('g:i A').' – '.$closes->format('g:i A'),
        ];
    }
}
