<?php

namespace Tests\Feature;

use App\Models\AttendancePunch;
use App\Models\Employee;
use App\Services\DtrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DtrServiceTest extends TestCase
{
    use RefreshDatabase;

    private function punch(string $empCode, string $dateTime): void
    {
        AttendancePunch::create(['emp_code' => $empCode, 'punch_time' => $dateTime]);
    }

    private function rowFor(array $dtr, string $date): array
    {
        foreach ($dtr['rows'] as $row) {
            if ($row['date'] === $date) {
                return $row;
            }
        }
        $this->fail("No row for {$date}");
    }

    public function test_exactly_ten_hours_has_no_undertime_or_overtime(): void
    {
        $this->punch('E1', '2026-09-01 08:00:00');
        $this->punch('E1', '2026-09-01 18:00:00');

        $dtr = (new DtrService)->build('E1', '2026-09-01', '2026-09-01');
        $row = $this->rowFor($dtr, '2026-09-01');

        $this->assertSame('10:00', $row['work_hrs']);
        $this->assertSame('', $row['undertime']);
        $this->assertSame('', $row['overtime']);
    }

    public function test_one_minute_short_is_undertime(): void
    {
        $this->punch('E1', '2026-09-01 08:00:00');
        $this->punch('E1', '2026-09-01 17:59:00');

        $dtr = (new DtrService)->build('E1', '2026-09-01', '2026-09-01');
        $row = $this->rowFor($dtr, '2026-09-01');

        $this->assertSame('0:01', $row['undertime']);
        $this->assertSame('', $row['overtime']);
    }

    public function test_ninety_minutes_over_is_overtime(): void
    {
        $this->punch('E1', '2026-09-01 08:00:00');
        $this->punch('E1', '2026-09-01 19:30:00');

        $dtr = (new DtrService)->build('E1', '2026-09-01', '2026-09-01');
        $row = $this->rowFor($dtr, '2026-09-01');

        $this->assertSame('1:30', $row['overtime']);
        $this->assertSame('', $row['undertime']);
    }

    public function test_tardy_uses_per_employee_start_time_with_no_grace_period(): void
    {
        Employee::create(['emp_code' => 'E1', 'name' => 'Test', 'start_time' => '08:00']);

        // 59 minutes late: below the 60-minute threshold, not tardy at all.
        $this->punch('E1', '2026-09-01 08:59:00');
        $this->punch('E1', '2026-09-01 18:59:00');

        $dtr = (new DtrService)->build('E1', '2026-09-01', '2026-09-01');
        $this->assertSame('', $this->rowFor($dtr, '2026-09-01')['tardy']);

        AttendancePunch::query()->delete();

        // Exactly 60 minutes late: at the threshold, full minutes count (no grace).
        $this->punch('E1', '2026-09-02 09:00:00');
        $this->punch('E1', '2026-09-02 19:00:00');

        $dtr = (new DtrService)->build('E1', '2026-09-02', '2026-09-02');
        $this->assertSame('1:00', $this->rowFor($dtr, '2026-09-02')['tardy']);

        AttendancePunch::query()->delete();

        // 80 minutes late: full minutes late, not just the excess over the threshold.
        $this->punch('E1', '2026-09-03 09:20:00');
        $this->punch('E1', '2026-09-03 19:20:00');

        $dtr = (new DtrService)->build('E1', '2026-09-03', '2026-09-03');
        $this->assertSame('1:20', $this->rowFor($dtr, '2026-09-03')['tardy']);
    }

    public function test_no_start_time_means_no_tardy(): void
    {
        Employee::create(['emp_code' => 'E1', 'name' => 'Test']);

        $this->punch('E1', '2026-09-01 11:00:00');
        $this->punch('E1', '2026-09-01 21:00:00');

        $dtr = (new DtrService)->build('E1', '2026-09-01', '2026-09-01');
        $this->assertSame('', $this->rowFor($dtr, '2026-09-01')['tardy']);
    }

    public function test_overnight_shift_lands_on_the_time_in_date(): void
    {
        $this->punch('E1', '2026-09-01 22:00:00');
        $this->punch('E1', '2026-09-02 06:00:00');

        $dtr = (new DtrService)->build('E1', '2026-09-01', '2026-09-02');

        $day1 = $this->rowFor($dtr, '2026-09-01');
        $this->assertSame('22:00', $day1['time_in']);
        $this->assertSame('6:00', $day1['time_out']);
        $this->assertTrue($day1['out_next_day']);
        $this->assertSame('8:00', $day1['work_hrs']);

        $day2 = $this->rowFor($dtr, '2026-09-02');
        $this->assertNull($day2['time_in']);
    }

    public function test_single_punch_is_incomplete(): void
    {
        $this->punch('E1', '2026-09-01 08:00:00');

        $dtr = (new DtrService)->build('E1', '2026-09-01', '2026-09-01');
        $row = $this->rowFor($dtr, '2026-09-01');

        $this->assertTrue($row['is_incomplete']);
        $this->assertSame('', $row['overtime']);
        $this->assertNull($row['time_out']);
    }

    public function test_double_tap_within_min_shift_minutes_is_incomplete(): void
    {
        $this->punch('E1', '2026-09-01 08:00:00');
        $this->punch('E1', '2026-09-01 08:03:00');

        $dtr = (new DtrService)->build('E1', '2026-09-01', '2026-09-01');
        $row = $this->rowFor($dtr, '2026-09-01');

        $this->assertTrue($row['is_incomplete']);
        $this->assertNull($row['time_out']);
    }

    public function test_previous_day_shift_tail_does_not_leak_into_date_from(): void
    {
        // Shift starts before the requested range — only date_to onward should be built.
        $this->punch('E1', '2026-08-31 22:00:00');
        $this->punch('E1', '2026-09-01 06:00:00');
        $this->punch('E1', '2026-09-01 20:00:00');

        $dtr = (new DtrService)->build('E1', '2026-09-01', '2026-09-01');
        $row = $this->rowFor($dtr, '2026-09-01');

        // The 06:00 punch was absorbed into the Aug 31 shift, so Sep 1 has no Time In
        // from that shift — only a fresh incomplete shift opened by the 20:00 punch.
        $this->assertSame('20:00', $row['time_in']);
        $this->assertNull($row['time_out']);
        $this->assertTrue($row['is_incomplete']);
    }
}
