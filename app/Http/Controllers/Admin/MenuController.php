<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MenuReorderRequest;
use App\Http\Requests\Admin\MenuRequest;
use App\Models\Menu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class MenuController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/menus', [
            // Named "items", not "menus" — the sidebar tree already shares a
            // "menus" prop on every request, and reusing the name would shadow it.
            'items' => Menu::query()->orderBy('sort')->get(),
            'icons' => Menu::ICONS,
            'routes' => Collection::make(array_keys(Route::getRoutes()->getRoutesByName()))
                ->sort()
                ->values(),
        ]);
    }

    public function store(MenuRequest $request): RedirectResponse
    {
        Menu::create([
            ...$request->validated(),
            'sort' => Menu::query()->where('parent_id', $request->input('parent_id'))->max('sort') + 1,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Menu item created.')]);

        return to_route('menus.index');
    }

    public function update(MenuRequest $request, Menu $menu): RedirectResponse
    {
        $menu->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Menu item updated.')]);

        return to_route('menus.index');
    }

    public function destroy(Menu $menu): RedirectResponse
    {
        abort_if($menu->children()->exists(), HttpResponse::HTTP_CONFLICT, 'Remove its sub-items first.');

        $menu->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Menu item deleted.')]);

        return to_route('menus.index');
    }

    public function reorder(MenuReorderRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            foreach ($request->input('items') as $item) {
                Menu::whereKey($item['id'])->update([
                    'parent_id' => $item['parent_id'],
                    'sort' => $item['sort'],
                ]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Menu order saved.')]);

        return to_route('menus.index');
    }
}
