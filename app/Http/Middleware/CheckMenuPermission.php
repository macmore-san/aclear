<?php

namespace App\Http\Middleware;

use App\Models\Menu;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckMenuPermission
{
    /** Route-name action suffix to the permission ability it requires. */
    private const ABILITY_MAP = [
        'index' => 'view',
        'show' => 'view',
        'create' => 'create',
        'store' => 'create',
        'edit' => 'edit',
        'update' => 'edit',
        'destroy' => 'delete',
    ];

    /**
     * Resolve the permission-guarded menu for a route (e.g. "users.update" -> "users"),
     * and deny the request unless the user holds the matching ability.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()?->getName();

        if ($routeName === null || ! str_contains($routeName, '.')) {
            return $next($request);
        }

        [$resource, $action] = explode('.', $routeName, 2);

        $menu = Menu::query()
            ->where('route', 'like', "{$resource}.%")
            ->whereNotNull('permission')
            ->first();

        // Routes with no matching menu (dashboard, settings, ...) are not menu-guarded.
        if ($menu === null) {
            return $next($request);
        }

        $ability = self::ABILITY_MAP[$action] ?? ($request->isMethod('GET') ? 'view' : 'edit');

        abort_unless($request->user()?->can("{$menu->permission}.{$ability}"), 403);

        return $next($request);
    }
}
