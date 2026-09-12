<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

/**
 * Seeds the "Time Records" menu group. Safe to re-run on its own
 * (php artisan db:seed --class=DtrMenuSeeder) against an already-seeded DB —
 * every row is looked up by its route before being created.
 */
class DtrMenuSeeder extends Seeder
{
    public function run(): void
    {
        $group = Menu::firstOrCreate(
            ['title' => 'Time Records'],
            ['icon' => 'ClipboardList', 'sort' => 2],
        );

        Menu::firstOrCreate(
            ['route' => 'employees.index'],
            ['parent_id' => $group->id, 'title' => 'Employees', 'icon' => 'Users', 'permission' => 'employees', 'sort' => 0],
        );

        Menu::firstOrCreate(
            ['route' => 'punches.index'],
            ['parent_id' => $group->id, 'title' => 'Upload Punches', 'icon' => 'FileText', 'permission' => 'punches', 'sort' => 1],
        );

        Menu::firstOrCreate(
            ['route' => 'dtr.index'],
            ['parent_id' => $group->id, 'title' => 'Print DTR', 'icon' => 'ClipboardList', 'permission' => 'dtr', 'sort' => 2],
        );
    }
}
