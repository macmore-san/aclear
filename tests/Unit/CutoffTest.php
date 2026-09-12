<?php

namespace Tests\Unit;

use App\Support\Cutoff;
use Carbon\Carbon;
use Tests\TestCase;

class CutoffTest extends TestCase
{
    public function test_cutoff_is_decided_in_the_app_timezone_not_utc(): void
    {
        $original = date_default_timezone_get();
        date_default_timezone_set('Asia/Manila');
        // 17:30 UTC on the 15th is already 01:30 on the 16th in Manila.
        Carbon::setTestNow(Carbon::parse('2026-09-15 17:30:00', 'UTC'));

        try {
            $this->assertSame(['from' => '2026-09-16', 'to' => '2026-09-30'], Cutoff::current());
        } finally {
            Carbon::setTestNow();
            date_default_timezone_set($original);
        }
    }
}
