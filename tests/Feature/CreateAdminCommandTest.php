<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_super_admin(): void
    {
        Role::create(['name' => 'Super Admin']);

        $this->artisan('app:create-admin')
            ->expectsQuestion('Name', 'Station Owner')
            ->expectsQuestion('Email', 'owner@aclear.test')
            ->expectsQuestion('Password', 'Str0ng!Passw0rd#2026')
            ->expectsQuestion('Confirm password', 'Str0ng!Passw0rd#2026')
            ->assertExitCode(0);

        $user = User::where('email', 'owner@aclear.test')->firstOrFail();
        $this->assertTrue($user->hasRole('Super Admin'));
        $this->assertNotSame('Str0ng!Passw0rd#2026', $user->password);
    }

    public function test_it_rejects_invalid_input_and_creates_nothing(): void
    {
        Role::create(['name' => 'Super Admin']);

        $this->artisan('app:create-admin')
            ->expectsQuestion('Name', 'Station Owner')
            ->expectsQuestion('Email', 'not-an-email')
            ->expectsQuestion('Password', 'Str0ng!Passw0rd#2026')
            ->expectsQuestion('Confirm password', 'something else')
            ->assertExitCode(1);

        $this->assertSame(0, User::count());
    }

    public function test_it_refuses_before_roles_are_seeded(): void
    {
        $this->artisan('app:create-admin')->assertExitCode(1);
    }
}
