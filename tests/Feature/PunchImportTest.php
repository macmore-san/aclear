<?php

namespace Tests\Feature;

use App\Models\AttendancePunch;
use App\Models\Employee;
use App\Models\Menu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PunchImportTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $menu = Menu::create(['title' => 'Upload Punches', 'route' => 'punches.index', 'permission' => 'punches', 'sort' => 0]);
        $user = User::factory()->create();
        $user->givePermissionTo('punches.view', 'punches.create');

        return $user;
    }

    private function csvFile(string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, $contents);

        return new UploadedFile($path, 'punches.csv', 'text/csv', null, true);
    }

    public function test_upload_imports_punches_and_creates_employees_from_name_column(): void
    {
        $user = $this->actingUser();

        $csv = "Ac-No,Name,sTime\n"
             ."E1,Jane Doe,2026-09-01 08:00:00\n"
             ."E1,Jane Doe,2026-09-01 18:00:00\n";

        $response = $this->actingAs($user)->post(route('punches.store'), [
            'files' => [$this->csvFile($csv)],
        ]);

        $response->assertOk();
        $response->assertJsonPath('imported', 2);
        $this->assertSame(2, AttendancePunch::count());
        $this->assertSame('Jane Doe', Employee::where('emp_code', 'E1')->first()->name);
    }

    public function test_reupload_counts_duplicates_and_does_not_error(): void
    {
        $user = $this->actingUser();

        $csv = "Ac-No,sTime\nE1,2026-09-01 08:00:00\n";

        $this->actingAs($user)->post(route('punches.store'), ['files' => [$this->csvFile($csv)]])
            ->assertJsonPath('imported', 1);

        $response = $this->actingAs($user)->post(route('punches.store'), ['files' => [$this->csvFile($csv)]]);

        $response->assertJsonPath('imported', 0);
        $response->assertJsonPath('files.0.duplicates', 1);
        $this->assertSame(1, AttendancePunch::count());
    }

    public function test_existing_employee_name_is_not_overwritten(): void
    {
        Employee::create(['emp_code' => 'E1', 'name' => 'Custom Name']);
        $user = $this->actingUser();

        $csv = "Ac-No,Name,sTime\nE1,CSV Name,2026-09-01 08:00:00\n";

        $this->actingAs($user)->post(route('punches.store'), ['files' => [$this->csvFile($csv)]]);

        $this->assertSame('Custom Name', Employee::where('emp_code', 'E1')->first()->name);
    }
}
