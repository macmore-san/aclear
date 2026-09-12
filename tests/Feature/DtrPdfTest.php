<?php

namespace Tests\Feature;

use App\Models\AttendancePunch;
use App\Models\Employee;
use App\Models\Menu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DtrPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_permission_can_download_pdf(): void
    {
        Menu::create(['title' => 'Print DTR', 'route' => 'dtr.index', 'permission' => 'dtr', 'sort' => 0]);
        Employee::create(['emp_code' => 'E1', 'name' => 'Jane Doe']);
        AttendancePunch::create(['emp_code' => 'E1', 'punch_time' => '2026-09-01 08:00:00']);
        AttendancePunch::create(['emp_code' => 'E1', 'punch_time' => '2026-09-01 18:00:00']);

        $user = User::factory()->create();
        $user->givePermissionTo('dtr.view');

        $response = $this->actingAs($user)->get(route('dtr.pdf', [
            'emp_codes' => ['E1'],
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-01',
        ]));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        Menu::create(['title' => 'Print DTR', 'route' => 'dtr.index', 'permission' => 'dtr', 'sort' => 0]);
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dtr.pdf', [
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-01',
        ]))->assertForbidden();
    }
}
