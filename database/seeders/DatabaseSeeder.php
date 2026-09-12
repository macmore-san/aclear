<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    // No WithoutModelEvents here: Menu's `saved` hook creates the matching
    // permissions, and seeding relies on that.

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->seedMenus();
        $this->seedRolesAndUsers();
        $this->call(DtrMenuSeeder::class);
    }

    private function seedMenus(): void
    {
        Menu::create(['title' => 'Dashboard', 'icon' => 'LayoutGrid', 'route' => 'dashboard', 'sort' => 0]);

        $accessControl = Menu::create(['title' => 'Access Control', 'icon' => 'ShieldCheck', 'sort' => 1]);

        Menu::create(['parent_id' => $accessControl->id, 'title' => 'Users', 'icon' => 'Users', 'route' => 'users.index', 'permission' => 'users', 'sort' => 0]);
        Menu::create(['parent_id' => $accessControl->id, 'title' => 'Roles', 'icon' => 'KeyRound', 'route' => 'roles.index', 'permission' => 'roles', 'sort' => 1]);
        Menu::create(['parent_id' => $accessControl->id, 'title' => 'Permissions', 'icon' => 'Lock', 'route' => 'permissions.index', 'permission' => 'permissions', 'sort' => 2]);
        Menu::create(['parent_id' => $accessControl->id, 'title' => 'Menus', 'icon' => 'ListTree', 'route' => 'menus.index', 'permission' => 'menus', 'sort' => 3]);
    }

    private function seedRolesAndUsers(): void
    {
        $superAdmin = Role::create(['name' => 'Super Admin']);
        Role::create(['name' => 'User']);

        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
        ])->assignRole($superAdmin);

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ])->assignRole('User');
    }
}
