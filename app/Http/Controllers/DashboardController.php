<?php

namespace App\Http\Controllers;

use App\Models\AttendancePunch;
use App\Models\Employee;
use App\Services\DtrService;
use App\Support\Cutoff;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private DtrService $dtrService) {}

    public function __invoke(): Response
    {
        $cutoff = Cutoff::current();

        $activeEmployees = Employee::where('is_active', true)->get(['emp_code']);
        $missingStartTime = Employee::where('is_active', true)
            ->whereNull('start_time')
            ->count();

        $lastPunch = AttendancePunch::orderByDesc('uploaded_at')->first(['source_file', 'uploaded_at']);

        // ponytail: one DtrService::build() per active employee per dashboard
        // load — fine at small-business headcount, cache or move to a single
        // query if this ever needs to scale past a few dozen staff.
        $incompleteThisCutoff = $activeEmployees->sum(
            fn (Employee $employee) => $this->dtrService->build(
                $employee->emp_code,
                $cutoff['from'],
                $cutoff['to'],
            )['incomplete_logs'],
        );

        return Inertia::render('dashboard', [
            'activeEmployees' => $activeEmployees->count(),
            'missingStartTime' => $missingStartTime,
            'totalPunches' => AttendancePunch::count(),
            'lastUpload' => $lastPunch ? [
                'source_file' => $lastPunch->source_file,
                'uploaded_at' => $lastPunch->uploaded_at,
            ] : null,
            'cutoff' => $cutoff,
            'incompleteThisCutoff' => $incompleteThisCutoff,
        ]);
    }
}
