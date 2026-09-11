<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;

/**
 * @property int $id
 * @property int|null $parent_id
 * @property string $title
 * @property string|null $icon
 * @property string|null $route
 * @property string|null $permission
 * @property int $sort
 */
#[Fillable(['parent_id', 'title', 'icon', 'route', 'permission', 'sort'])]
class Menu extends Model
{
    /** The CRUD abilities generated for every menu permission group. */
    public const ABILITIES = ['view', 'create', 'edit', 'delete'];

    /**
     * Curated lucide-react icon names selectable for a menu item.
     * Keep in sync with resources/js/lib/icons.ts.
     */
    public const ICONS = [
        'LayoutGrid', 'Users', 'ShieldCheck', 'KeyRound', 'ListTree', 'Settings',
        'FileText', 'Folder', 'FolderGit2', 'BookOpen', 'BarChart3', 'Bell',
        'Calendar', 'Mail', 'MessageSquare', 'CreditCard', 'ShoppingCart', 'Package',
        'Building2', 'Home', 'Database', 'Globe', 'Image', 'Tag', 'Star', 'Flag',
        'Lock', 'Wrench', 'Activity', 'ClipboardList',
    ];

    /**
     * @return BelongsTo<Menu, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Menu, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort');
    }

    protected static function booted(): void
    {
        static::saved(function (self $menu): void {
            if ($menu->permission === null) {
                return;
            }

            foreach (self::ABILITIES as $ability) {
                Permission::findOrCreate("{$menu->permission}.{$ability}");
            }
        });
    }

    /**
     * Build the nested menu tree visible to the given user.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function tree(User $user): array
    {
        $menus = self::query()->orderBy('sort')->get();

        return self::buildTree($menus, null, $user);
    }

    /**
     * @param  Collection<int, self>  $menus
     * @return array<int, array<string, mixed>>
     */
    protected static function buildTree(Collection $menus, ?int $parentId, User $user): array
    {
        $items = [];

        foreach ($menus->where('parent_id', $parentId) as $menu) {
            if ($menu->permission !== null && ! $user->can("{$menu->permission}.view")) {
                continue;
            }

            $children = self::buildTree($menus, $menu->id, $user);

            // A group with no route and no visible children has nothing to show.
            if ($menu->route === null && $children === []) {
                continue;
            }

            $items[] = [
                'id' => $menu->id,
                'title' => $menu->title,
                'icon' => $menu->icon,
                'href' => $menu->route !== null && Route::has($menu->route)
                    ? route($menu->route)
                    : null,
                'children' => $children,
            ];
        }

        return $items;
    }
}
