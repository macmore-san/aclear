<?php

namespace App\Services;

use App\Models\AttendancePunch;
use App\Models\Employee;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Computes a DTR for an employee with no fixed schedule: a flat required
 * duration per shift, no lunch deduction, no grace period, and shifts that
 * may cross midnight. See CLAUDE.md for the full rules table and worked
 * examples of the shift-pairing algorithm below.
 */
class DtrService
{
    /** @return array<string, mixed> */
    public function build(string $empCode, string $dateFrom, string $dateTo): array
    {
        $employee = Employee::where('emp_code', $empCode)->first();

        $requiredMinutes = (int) config('dtr.required_minutes');
        $maxShiftHours = (int) config('dtr.max_shift_hours');
        $minShiftMinutes = (int) config('dtr.min_shift_minutes');

        // Pull a window wider than the requested range so a shift that starts the
        // evening before date_from, or ends the morning after date_to, still pairs
        // correctly — it's dropped again below by its Time In date.
        $rangeStart = Carbon::parse($dateFrom)->startOfDay()->subHours($maxShiftHours);
        $rangeEnd = Carbon::parse($dateTo)->endOfDay()->addHours($maxShiftHours);

        $punches = AttendancePunch::where('emp_code', $empCode)
            ->whereBetween('punch_time', [$rangeStart, $rangeEnd])
            ->orderBy('punch_time')
            ->get();

        // Strip seconds so displayed time and computed minutes are consistent.
        $times = $punches->map(fn ($p) => $p->punch_time->copy()->second(0))
            ->sortBy(fn ($t) => $t->getTimestamp())
            ->values();

        $shifts = $this->pairShifts($times->all(), $maxShiftHours, $minShiftMinutes);

        // Group by Time In date, keeping only shifts that *start* inside the requested
        // range — this is what drops the tail of a previous-day overnight shift from
        // date_from.
        $shiftsByDate = [];
        foreach ($shifts as $shift) {
            $dateKey = $shift['in']->format('Y-m-d');
            if ($dateKey >= $dateFrom && $dateKey <= $dateTo) {
                $shiftsByDate[$dateKey][] = $shift;
            }
        }

        $rows = [];
        $period = Carbon::parse($dateFrom)->toPeriod(Carbon::parse($dateTo));

        foreach ($period as $day) {
            $dateKey = $day->format('Y-m-d');
            $rows[] = $this->computeRow($day, $shiftsByDate[$dateKey] ?? [], $employee, $requiredMinutes);
        }

        return [
            'employee' => $employee ? $this->formatEmployee($employee) : null,
            'emp_code' => $empCode,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'rows' => $rows,
            'total_work_hrs' => $this->sumMinutes(array_column($rows, '_work_minutes')),
            'total_tardy_hrs' => $this->sumMinutes(array_column($rows, '_tardy_minutes')),
            'total_ut_hrs' => $this->sumMinutes(array_column($rows, '_undertime_minutes')),
            'total_ot_hrs' => $this->sumMinutes(array_column($rows, '_overtime_minutes')),
            'incomplete_logs' => count(array_filter($rows, fn ($r) => $r['is_incomplete'])),
        ];
    }

    /**
     * Pairs a sorted list of punch timestamps into shifts.
     *
     * A shift opens at a punch. Every later punch within $maxShiftHours of that
     * opening punch joins the same shift, and the last one absorbed becomes Time
     * Out — this is what lets a 22:00 -> 06:00 overnight shift stay together
     * instead of being read as two separate incomplete days. The punch right
     * after that window opens the next shift.
     *
     * Example ($maxShiftHours = 16): punches [Mon 22:00, Tue 06:00, Tue 20:00]
     * -> shift 1 = Mon 22:00 -> Tue 06:00 (Tue 20:00 is outside the 16h window
     * from Mon 22:00, so it starts shift 2, currently open/incomplete).
     *
     * // ponytail: fixed-window ceiling — a missed Time Out followed by a Time In
     * // less than $maxShiftHours later pairs into one (wrong) shift. Upgrade path:
     * // use the employee's start_time to decide where a new shift should begin.
     *
     * @param  array<int, CarbonInterface>  $times
     * @return array<int, array{in: CarbonInterface, out: CarbonInterface|null}>
     */
    private function pairShifts(array $times, int $maxShiftHours, int $minShiftMinutes): array
    {
        $shifts = [];
        $n = count($times);
        $i = 0;

        while ($i < $n) {
            $in = $times[$i];
            $windowEnd = $in->copy()->addHours($maxShiftHours);
            $lastInside = $i;
            $j = $i + 1;

            while ($j < $n && $times[$j]->lte($windowEnd)) {
                $lastInside = $j;
                $j++;
            }

            $out = $lastInside > $i ? $times[$lastInside] : null;

            // A Time Out closer than min_shift_minutes to Time In is a double-tap
            // (e.g. the device beeped twice), not a real completed shift.
            if ($out !== null && $in->diffInMinutes($out) < $minShiftMinutes) {
                $out = null;
            }

            $shifts[] = ['in' => $in, 'out' => $out];
            $i = $lastInside + 1;
        }

        return $shifts;
    }

    /**
     * @param  array<int, array{in: CarbonInterface, out: CarbonInterface|null}>  $dayShifts
     * @return array<string, mixed>
     */
    private function computeRow(CarbonInterface $day, array $dayShifts, ?Employee $employee, int $requiredMinutes): array
    {
        $dateKey = $day->format('Y-m-d');

        $base = [
            'date' => $dateKey,
            'day_label' => $day->format('m/d/Y').' '.$day->format('D'),
            'time_in' => null,
            'time_out' => null,
            'out_next_day' => false,
            'work_hrs' => '',
            'tardy' => '',
            'undertime' => '',
            'overtime' => '',
            'remarks' => '',
            'is_incomplete' => false,
            '_work_minutes' => 0,
            '_tardy_minutes' => 0,
            '_undertime_minutes' => 0,
            '_overtime_minutes' => 0,
        ];

        if ($dayShifts === []) {
            return $base;
        }

        $timeIn = null;
        $timeOut = null;
        $isIncomplete = false;
        $workMinutes = 0;

        foreach ($dayShifts as $shift) {
            if ($timeIn === null || $shift['in']->lt($timeIn)) {
                $timeIn = $shift['in'];
            }

            if ($shift['out'] === null) {
                $isIncomplete = true;

                continue;
            }

            if ($timeOut === null || $shift['out']->gt($timeOut)) {
                $timeOut = $shift['out'];
            }

            $workMinutes += (int) $shift['in']->diffInMinutes($shift['out']);
        }

        $undertimeMinutes = max(0, (int) ($requiredMinutes - $workMinutes));
        $overtimeMinutes = $isIncomplete ? 0 : max(0, (int) ($workMinutes - $requiredMinutes));

        $tardyMinutes = $this->computeTardy($timeIn, $dateKey, $employee);

        $outNextDay = $timeOut !== null && $timeOut->format('Y-m-d') !== $dateKey;

        $remarks = $isIncomplete ? '*' : '';

        return array_merge($base, [
            'time_in' => $timeIn->format('G:i'),
            'time_out' => $timeOut?->format('G:i'),
            'out_next_day' => $outNextDay,
            'work_hrs' => $this->minutesToHhmm($workMinutes),
            'tardy' => $tardyMinutes > 0 ? $this->minutesToHhmm($tardyMinutes) : '',
            'undertime' => $undertimeMinutes > 0 ? $this->minutesToHhmm($undertimeMinutes) : '',
            'overtime' => $overtimeMinutes > 0 ? $this->minutesToHhmm($overtimeMinutes) : '',
            'remarks' => $remarks,
            'is_incomplete' => $isIncomplete,
            '_work_minutes' => $workMinutes,
            '_tardy_minutes' => $tardyMinutes,
            '_undertime_minutes' => $undertimeMinutes,
            '_overtime_minutes' => $overtimeMinutes,
        ]);
    }

    /**
     * Tardy is measured against the employee's own start_time (there is no
     * company-wide schedule) — no start_time set means tardy can't be computed at
     * all. The nearest occurrence of start_time (same day, the day before, or the
     * day after) is used as the expected arrival, so an overnight shift's evening
     * start still compares correctly. Below the configured threshold it isn't
     * tardy at all; at or above it, the full minutes late count — no grace period.
     */
    private function computeTardy(CarbonInterface $timeIn, string $dateKey, ?Employee $employee): int
    {
        if ($employee === null || $employee->start_time === null) {
            return 0;
        }

        $startTimeOfDay = Carbon::parse($employee->start_time)->format('H:i:s');

        // Nearest occurrence of start_time to timeIn, checking the day before/of/after
        // so an overnight shift's evening start still compares correctly.
        $expectedTimestamp = null;
        $bestDiff = null;

        $timeInTimestamp = $timeIn->getTimestamp();

        foreach ([-1, 0, 1] as $offsetDays) {
            $candidateTimestamp = Carbon::parse($dateKey)->addDays($offsetDays)
                ->setTimeFromTimeString($startTimeOfDay)->getTimestamp();
            $diff = abs($candidateTimestamp - $timeInTimestamp);

            if ($bestDiff === null || $diff < $bestDiff) {
                $bestDiff = $diff;
                $expectedTimestamp = $candidateTimestamp;
            }
        }

        // Signed manually (not diffInMinutes' built-in sign) so "late" is unambiguously
        // positive: timeIn after expected. Negative (early arrival) never counts as tardy.
        $lateMinutes = intdiv($timeInTimestamp - $expectedTimestamp, 60);

        $threshold = (int) config('dtr.tardy_threshold_minutes');

        return $lateMinutes >= $threshold ? $lateMinutes : 0;
    }

    private function minutesToHhmm(int $minutes): string
    {
        return sprintf('%d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    /** @param  array<int, int>  $minuteValues */
    private function sumMinutes(array $minuteValues): string
    {
        return $this->minutesToHhmm((int) array_sum($minuteValues));
    }

    /** @return array<string, mixed> */
    private function formatEmployee(Employee $emp): array
    {
        return [
            'emp_code' => $emp->emp_code,
            'full_name' => $emp->name,
            'position' => $emp->position ?? '',
            'department' => $emp->department ?? '',
            'start_time' => $emp->start_time,
        ];
    }
}
