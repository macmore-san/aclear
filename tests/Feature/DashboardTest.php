<?php

namespace Tests\Feature;

use App\Models\AttendancePunch;
use App\Models\Employee;
use App\Models\User;
use App\Support\Cutoff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_reports_station_state(): void
    {
        $user = User::factory()->create();

        $withStartTime = Employee::create([
            'emp_code' => 'E1', 'name' => 'Jane Doe', 'is_active' => true, 'start_time' => '08:00',
        ]);
        Employee::create(['emp_code' => 'E2', 'name' => 'John Roe', 'is_active' => true]); // no start_time
        Employee::create(['emp_code' => 'E3', 'name' => 'Retired', 'is_active' => false]);

        $cutoff = Cutoff::current();

        // One complete, on-time shift inside the current cut-off.
        AttendancePunch::create([
            'emp_code' => $withStartTime->emp_code,
            'punch_time' => $cutoff['from'].' 08:00:00',
            'source_file' => 'sept.csv',
        ]);
        AttendancePunch::create([
            'emp_code' => $withStartTime->emp_code,
            'punch_time' => $cutoff['from'].' 18:00:00',
            'source_file' => 'sept.csv',
        ]);
        // An incomplete shift (no Time Out) for the other employee.
        AttendancePunch::create([
            'emp_code' => 'E2',
            'punch_time' => $cutoff['from'].' 09:00:00',
            'source_file' => 'sept.csv',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('activeEmployees', 2)
            ->where('missingStartTime', 1)
            ->where('totalPunches', 3)
            ->where('lastUpload.source_file', 'sept.csv')
            ->where('cutoff.from', $cutoff['from'])
            ->where('cutoff.to', $cutoff['to'])
            ->where('incompleteThisCutoff', 1)
        );
    }
}
