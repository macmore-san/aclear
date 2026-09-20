<?php

use App\Models\Menu;
use Illuminate\Database\Migrations\Migration;

/**
 * Adds System → Updates to the sidebar.
 *
 * A migration rather than a seeder because updates run `migrate --force` but never
 * `db:seed` (see run-update.ps1 / update.bat), so a seeder would only ever reach
 * fresh installs — existing ones would update to a version whose Updates page
 * exists but is unreachable.
 *
 * Menu's `saved` hook creates the matching updates.* permissions; Super Admin
 * bypasses permission checks entirely via Gate::before.
 */
return new class extends Migration
{
    public function up(): void
    {
        $group = Menu::firstOrCreate(
            ['title' => 'System'],
            ['icon' => 'Wrench', 'sort' => 3],
        );

        Menu::firstOrCreate(
            ['route' => 'updates.index'],
            [
                'parent_id' => $group->id,
                'title' => 'Updates',
                'icon' => 'Package',
                'permission' => 'updates',
                'sort' => 0,
            ],
        );
    }

    public function down(): void
    {
        Menu::where('route', 'updates.index')->delete();
        // Only remove the group if this migration's item was the only thing in it.
        Menu::where('title', 'System')->whereDoesntHave('children')->delete();
    }
};
