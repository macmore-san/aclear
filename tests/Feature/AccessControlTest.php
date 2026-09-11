<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_is_disabled()
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register')->assertNotFound();
    }

    public function test_user_without_permission_cannot_view_users_index()
    {
        $this->seedUsersMenu();
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('users.index'))->assertForbidden();
    }

    public function test_user_with_view_permission_can_view_but_not_create()
    {
        $this->seedUsersMenu();
        $user = User::factory()->create();
        $user->givePermissionTo('users.view');

        $this->actingAs($user)->get(route('users.index'))->assertOk();
        $this->actingAs($user)->post(route('users.store'), [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertForbidden();
    }

    public function test_super_admin_can_create_a_user_with_a_role()
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::create(['name' => 'Super Admin']));
        $role = Role::create(['name' => 'Editor']);

        $response = $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'roles' => [$role->name],
        ]);

        $response->assertRedirect(route('users.index'));
        $created = User::whereEmail('new@example.com')->firstOrFail();
        $this->assertTrue($created->hasRole('Editor'));
    }

    public function test_menus_shared_prop_hides_unpermitted_items()
    {
        Menu::create(['title' => 'Dashboard', 'route' => 'dashboard', 'sort' => 0]);
        Menu::create(['title' => 'Users', 'route' => 'users.index', 'permission' => 'users', 'sort' => 1]);

        $user = User::factory()->create();

        $tree = Menu::tree($user);

        $this->assertCount(1, $tree);
        $this->assertSame('Dashboard', $tree[0]['title']);
    }

    public function test_reorder_saves_parent_and_sort_and_rejects_three_level_nesting()
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::create(['name' => 'Super Admin']));

        $top = Menu::create(['title' => 'Group', 'sort' => 0]);
        $child = Menu::create(['title' => 'Child', 'parent_id' => $top->id, 'sort' => 0]);
        $other = Menu::create(['title' => 'Other', 'sort' => 1]);

        // Valid: move "Other" under "Group" as its second child.
        $this->actingAs($admin)->put(route('menus.reorder'), [
            'items' => [
                ['id' => $other->id, 'parent_id' => $top->id, 'sort' => 1],
            ],
        ])->assertRedirect(route('menus.index'));

        $this->assertSame($top->id, $other->fresh()->parent_id);

        // Invalid: nesting a third level under "Child".
        $response = $this->actingAs($admin)->put(route('menus.reorder'), [
            'items' => [
                ['id' => $other->fresh()->id, 'parent_id' => $child->id, 'sort' => 0],
            ],
        ]);

        $response->assertSessionHasErrors('items.0.parent_id');
    }

    public function test_menus_admin_page_does_not_shadow_the_shared_sidebar_tree_prop()
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::create(['name' => 'Super Admin']));
        Menu::create(['title' => 'Dashboard', 'route' => 'dashboard', 'sort' => 0]);

        // Regression: MenuController::index used to return its flat list under
        // the same "menus" key the sidebar tree is shared under, silently
        // replacing the tree and crashing <NavMain> on the client.
        $response = $this->actingAs($admin)->get(route('menus.index'));

        $response->assertOk();
        $page = $response->viewData('page')['props'];

        $this->assertIsArray($page['items']);
        $this->assertArrayHasKey('children', $page['menus'][0]);
    }

    /** The CheckMenuPermission middleware only gates a route once a menu claims its prefix. */
    private function seedUsersMenu(): void
    {
        Menu::create(['title' => 'Users', 'route' => 'users.index', 'permission' => 'users', 'sort' => 0]);
    }
}
