<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /** The built-in role that must always retain full access. */
    private const PROTECTED_ROLE = 'Super Admin';

    public function index(): Response
    {
        return Inertia::render('admin/roles', [
            'roles' => Role::query()->with('permissions:id,name')->orderBy('name')->get(),
            'permissions' => Permission::query()->orderBy('name')->pluck('name'),
        ]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $role = Role::create(['name' => $request->string('name')]);
        $role->syncPermissions($request->input('permissions', []));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role created.')]);

        return to_route('roles.index');
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        abort_if($role->name === self::PROTECTED_ROLE, 403, 'The Super Admin role cannot be modified.');

        $role->update(['name' => $request->string('name')]);
        $role->syncPermissions($request->input('permissions', []));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role updated.')]);

        return to_route('roles.index');
    }

    public function destroy(Role $role): RedirectResponse
    {
        abort_if($role->name === self::PROTECTED_ROLE, 403, 'The Super Admin role cannot be deleted.');

        $role->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role deleted.')]);

        return to_route('roles.index');
    }
}
