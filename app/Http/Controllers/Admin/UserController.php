<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $users = User::query()
            ->with('roles:id,name')
            ->when($request->string('search')->trim()->isNotEmpty(), fn ($query) => $query->where(
                fn ($query) => $query
                    ->where('name', 'like', '%'.$request->string('search')->trim().'%')
                    ->orWhere('email', 'like', '%'.$request->string('search')->trim().'%'),
            ))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/users', [
            'users' => $users,
            'roles' => Role::query()->pluck('name'),
            'filters' => $request->only('search'),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $user = User::create([
            ...$request->safe()->except(['roles']),
            'password' => $request->string('password'),
        ]);

        $user->syncRoles($request->input('roles', []));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User created.')]);

        return to_route('users.index');
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $user->fill($request->safe()->except(['roles', 'password']));

        if ($request->filled('password')) {
            $user->password = $request->string('password');
        }

        $user->save();
        $user->syncRoles($request->input('roles', []));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User updated.')]);

        return to_route('users.index');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === $request->user()->id, 403, "You can't delete your own account.");

        $user->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User deleted.')]);

        return to_route('users.index');
    }
}
