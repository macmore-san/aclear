<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Semi-monthly payroll cut-off: the 1st-15th, or the 16th-last day of month.
 * Mirrors resources/js/lib/cutoff.ts — keep both in sync if this ever changes.
 */
class Cutoff
{
    /** @return array{from: string, to: string} */
    public static function current(): array
    {
        $today = Carbon::today();

        return $today->day <= 15
            ? ['from' => $today->copy()->startOfMonth()->toDateString(), 'to' => $today->copy()->startOfMonth()->addDays(14)->toDateString()]
            : ['from' => $today->copy()->startOfMonth()->addDays(15)->toDateString(), 'to' => $today->copy()->endOfMonth()->toDateString()];
    }
}
