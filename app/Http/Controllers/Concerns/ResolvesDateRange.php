<?php

namespace App\Http\Controllers\Concerns;

use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Throwable;

trait ResolvesDateRange
{
    private const RANGES = ['today', 'yesterday', 'week', 'last_week', 'month', 'last_month', 'all', 'custom'];

    /**
     * @param  'today'|'yesterday'|'week'|'last_week'|'month'|'last_month'|'all'|'custom'  $default
     * @return array{0: CarbonInterface|null, 1: CarbonInterface|null, 2: array{range: string, from: string|null, to: string|null}}
     */
    private function resolveRange(Request $request, string $default = 'today'): array
    {
        $range = $request->query('range', $default);

        if (! in_array($range, self::RANGES, true)) {
            $range = $default;
        }

        $now = now();

        [$start, $end] = match ($range) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'last_week' => [$now->copy()->subWeek()->startOfWeek(), $now->copy()->subWeek()->endOfWeek()],
            'month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            'all' => [null, null],
            'custom' => $this->resolveCustomRange($request, $now),
        };

        return [$start, $end, [
            'range' => $range,
            'from' => $start?->toDateString(),
            'to' => $end?->toDateString(),
        ]];
    }

    /**
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    private function resolveCustomRange(Request $request, CarbonInterface $now): array
    {
        try {
            $start = $request->query('from') ? Carbon::parse($request->query('from'))->startOfDay() : $now->copy()->startOfDay();
            $end = $request->query('to') ? Carbon::parse($request->query('to'))->endOfDay() : $now->copy()->endOfDay();
        } catch (Throwable) {
            return [$now->copy()->startOfDay(), $now->copy()->endOfDay()];
        }

        return $start->gt($end) ? [$end->copy()->startOfDay(), $start->copy()->endOfDay()] : [$start, $end];
    }
}
