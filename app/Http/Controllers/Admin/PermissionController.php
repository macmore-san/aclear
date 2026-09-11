<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PermissionRequest;
use App\Models\Menu;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index(): Response
    {
        $menuPrefixes = Menu::query()->whereNotNull('permission')->pluck('permission');

        return Inertia::render('admin/permissions', [
            // Menu-owned permissions (e.g. "users.view") are managed from the Menus page.
            'permissions' => Permission::query()
                ->orderBy('name')
                ->get()
                ->reject(fn (Permission $permission) => $menuPrefixes->contains(
                    fn (string $prefix) => str_starts_with($permission->name, "{$prefix}."),
                ))
                ->values(),
        ]);
    }

    public function store(PermissionRequest $request): RedirectResponse
    {
        Permission::create(['name' => $request->string('name')]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Permission created.')]);

        return to_route('permissions.index');
    }

    public function update(PermissionRequest $request, Permission $permission): RedirectResponse
    {
        $permission->update(['name' => $request->string('name')]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Permission updated.')]);

        return to_route('permissions.index');
    }

    public function destroy(Permission $permission): RedirectResponse
    {
        $permission->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Permission deleted.')]);

        return to_route('permissions.index');
    }
}
