<?php

namespace App\Services\Admin;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/** Resolve named reporting presets into inclusive, timezone-aware date boundaries. */
class AdminDateRangeService
{
    /** @return array{range: string, from: CarbonImmutable|null, to: CarbonImmutable|null} */
    public function resolve(array $filters): array
    {
        $timezone = config('app.timezone');
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $range = $filters['range'] ?? 'today';

        if ($range === 'all') {
            return [
                'range' => $range,
                'from' => null,
                'to' => null,
            ];
        }

        if ($range === 'last_24_hours') {
            return [
                'range' => $range,
                'from' => CarbonImmutable::now($timezone)->subDay(),
                'to' => CarbonImmutable::now($timezone),
            ];
        }

        if ($range === 'custom') {
            $from = CarbonImmutable::createFromFormat('!Y-m-d', $filters['from_date'], $timezone);
            $to = CarbonImmutable::createFromFormat('!Y-m-d', $filters['to_date'], $timezone);

            if ($from->diffInDays($to) > 3660) {
                throw ValidationException::withMessages([
                    'to_date' => ['Choose a date range of ten years or less.'],
                ]);
            }

            return [
                'range' => $range,
                'from' => $from->startOfDay(),
                'to' => $to->endOfDay(),
            ];
        }

        [$from, $to] = match ($range) {
            'yesterday' => [$today->subDay(), $today->subDay()->endOfDay()],
            'this_week' => [$today->startOfWeek(), $today->endOfWeek()],
            'last_week' => [
                $today->subWeek()->startOfWeek(),
                $today->subWeek()->endOfWeek(),
            ],
            'this_month' => [$today->startOfMonth(), $today->endOfMonth()],
            'last_month' => [
                $today->subMonthNoOverflow()->startOfMonth(),
                $today->subMonthNoOverflow()->endOfMonth(),
            ],
            'this_year' => [$today->startOfYear(), $today->endOfYear()],
            'last_year' => [
                $today->subYearNoOverflow()->startOfYear(),
                $today->subYearNoOverflow()->endOfYear(),
            ],
            default => [$today, $today->endOfDay()],
        };

        return [
            'range' => $range,
            'from' => $from,
            'to' => $to,
        ];
    }
}
