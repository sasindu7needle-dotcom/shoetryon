<?php

namespace Tests\Feature\Admin;

use App\Models\Shoe;
use App\Models\User;
use Database\Seeders\ShoeCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShoeLibraryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_library_lists_every_shoe_in_order_including_removed_ones(): void
    {
        Shoe::factory()->hidden()->create(['name' => 'Second Shoe', 'position' => 2]);
        Shoe::factory()->create(['name' => 'First Shoe', 'position' => 1]);

        $response = $this->actingAs($this->admin)->withoutVite()->get(route('admin.shoes.index'));

        $response->assertOk();
        $response->assertSeeInOrder(['First Shoe', 'Second Shoe']);
    }

    public function test_dashboard_counts_shoes_and_previews_only_those_on_the_site(): void
    {
        Shoe::factory()->hidden()->create(['name' => 'Hidden Heel']);
        Shoe::factory()->create(['name' => 'Listed Loafer']);

        $response = $this->actingAs($this->admin)->withoutVite()->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('stats', fn (array $stats) => $stats['total'] === 2 && $stats['visible'] === 1 && $stats['hidden'] === 1);
        $response->assertSee('Listed Loafer');
        $response->assertDontSee('Hidden Heel');
    }

    public function test_sidebar_links_to_the_shoe_library_and_marks_the_current_page(): void
    {
        $response = $this->actingAs($this->admin)->withoutVite()->get(route('admin.shoes.index'));

        $this->assertMatchesRegularExpression(
            '#href="'.preg_quote(route('admin.shoes.index'), '#').'"\s+aria-current="page"#',
            $response->getContent(),
        );
        $this->assertDoesNotMatchRegularExpression(
            '#href="'.preg_quote(route('admin.dashboard'), '#').'"\s+aria-current#',
            $response->getContent(),
        );
    }

    public function test_edit_page_renders(): void
    {
        $shoe = Shoe::factory()->create();

        $this->actingAs($this->admin)->withoutVite()->get(route('admin.shoes.edit', $shoe))->assertOk()->assertSee($shoe->name);
    }

    public function test_admin_can_update_a_shoe(): void
    {
        $shoe = Shoe::factory()->create();

        $response = $this->actingAs($this->admin)->put(route('admin.shoes.update', $shoe), [
            'name' => 'Renamed Runner',
            'is_visible' => '0',
            'position' => $shoe->position,
            'fit' => [
                'scale' => '80.5',
                'rotation' => ['0', '-90', '0'],
                'position' => ['0.7', '-1.8', '-4.8'],
                'stretch' => ['1.1', '1', '1'],
            ],
        ]);

        $response->assertRedirect(route('admin.shoes.index'));
        $shoe->refresh();
        $this->assertSame('Renamed Runner', $shoe->name);
        $this->assertFalse($shoe->is_visible);
        $this->assertEquals(['scale' => 80.5, 'rotation' => [0, -90, 0], 'position' => [0.7, -1.8, -4.8], 'stretch' => [1.1, 1, 1]], $shoe->fit);
    }

    public function test_a_stretch_of_one_is_not_stored(): void
    {
        $shoe = Shoe::factory()->create();

        $this->actingAs($this->admin)->put(route('admin.shoes.update', $shoe), [
            'name' => $shoe->name,
            'is_visible' => '1',
            'position' => $shoe->position,
            'fit' => ['scale' => '90', 'rotation' => [0, 0, 0], 'position' => [0, 0, 0], 'stretch' => [1, 1, 1]],
        ]);

        $this->assertArrayNotHasKey('stretch', $shoe->refresh()->fit);
    }

    public function test_using_the_studio_placement_clears_the_fit_and_ignores_its_fields(): void
    {
        $shoe = Shoe::factory()->create();

        $response = $this->actingAs($this->admin)->put(route('admin.shoes.update', $shoe), [
            'name' => $shoe->name,
            'is_visible' => '1',
            'position' => $shoe->position,
            'use_studio_fit' => '1',
            'fit' => ['scale' => '', 'rotation' => [0, 0, 0], 'position' => [0, 0, 0], 'stretch' => [1, 1, 1]],
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertNull($shoe->refresh()->fit);
    }

    public function test_edit_page_ticks_the_studio_placement_for_a_shoe_without_a_fit(): void
    {
        $shoe = Shoe::factory()->create(['fit' => null]);

        $this->actingAs($this->admin)->withoutVite()->get(route('admin.shoes.edit', $shoe))
            ->assertSee('name="use_studio_fit" value="1" checked', false);
    }

    public function test_update_rejects_invalid_values(): void
    {
        $shoe = Shoe::factory()->create();

        $response = $this->actingAs($this->admin)->put(route('admin.shoes.update', $shoe), [
            'name' => '',
            'position' => 0,
            'fit' => ['scale' => '0', 'rotation' => ['a', 0, 0], 'position' => [0, 0], 'stretch' => [1, 1, 1]],
        ]);

        $response->assertSessionHasErrors(['name', 'position', 'fit.scale', 'fit.rotation.0', 'fit.position']);
    }

    public function test_admin_can_delete_a_shoe(): void
    {
        $shoe = Shoe::factory()->create();

        $this->actingAs($this->admin)->delete(route('admin.shoes.destroy', $shoe))->assertRedirect(route('admin.shoes.index'));

        $this->assertModelMissing($shoe);
    }

    public function test_admin_can_hide_a_shoe_from_the_site_and_unhide_it(): void
    {
        $shoe = Shoe::factory()->create();

        $this->actingAs($this->admin)->delete(route('admin.shoes.visibility.destroy', $shoe));
        $this->assertFalse($shoe->refresh()->is_visible);

        $this->actingAs($this->admin)->post(route('admin.shoes.visibility.store', $shoe));
        $this->assertTrue($shoe->refresh()->is_visible);
    }

    public function test_admin_can_move_a_shoe_up_and_down(): void
    {
        [$first, $second, $third] = Shoe::factory()->count(3)->sequence(['position' => 1], ['position' => 2], ['position' => 3])->create();

        $this->actingAs($this->admin)->patch(route('admin.shoes.position.update', $third), ['direction' => 'up']);
        $this->assertSame([$first->id, $third->id, $second->id], Shoe::query()->ordered()->pluck('id')->all());

        $this->actingAs($this->admin)->patch(route('admin.shoes.position.update', $first), ['direction' => 'down']);
        $this->assertSame([$third->id, $first->id, $second->id], Shoe::query()->ordered()->pluck('id')->all());
    }

    public function test_moving_the_first_shoe_up_keeps_the_order(): void
    {
        [$first, $second] = Shoe::factory()->count(2)->sequence(['position' => 1], ['position' => 2])->create();

        $this->actingAs($this->admin)->patch(route('admin.shoes.position.update', $first), ['direction' => 'up']);

        $this->assertSame([$first->id, $second->id], Shoe::query()->ordered()->pluck('id')->all());
    }

    public function test_move_rejects_unknown_directions(): void
    {
        $shoe = Shoe::factory()->create();

        $this->actingAs($this->admin)->patch(route('admin.shoes.position.update', $shoe), ['direction' => 'sideways'])->assertSessionHasErrors('direction');
    }

    public function test_users_who_are_not_admins_cannot_change_the_library(): void
    {
        $shoe = Shoe::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)->delete(route('admin.shoes.destroy', $shoe))->assertForbidden();
        $this->actingAs($user)->delete(route('admin.shoes.visibility.destroy', $shoe))->assertForbidden();

        $this->assertModelExists($shoe);
        $this->assertTrue($shoe->refresh()->is_visible);
    }

    public function test_catalog_seeder_fills_an_empty_library_once(): void
    {
        $this->seed(ShoeCatalogSeeder::class);
        $this->assertSame(count(config('shoes')), Shoe::query()->count());

        $this->seed(ShoeCatalogSeeder::class);
        $this->assertSame(count(config('shoes')), Shoe::query()->count());
    }

    public function test_admin_command_creates_an_admin_who_can_sign_in_with_their_username(): void
    {
        $this->artisan('shopar:admin', ['username' => 'owner', '--password' => 'secret-password'])->assertSuccessful();

        $this->post(route('admin.login.store'), ['login' => 'owner', 'password' => 'secret-password'])
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_admin_command_resets_an_existing_admins_password(): void
    {
        $admin = User::factory()->admin()->create(['username' => 'owner']);

        $this->artisan('shopar:admin', ['username' => 'owner', '--password' => 'new-secret-password'])->assertSuccessful();

        $this->assertSame(1, User::query()->where('username', 'owner')->count());
        $this->post(route('admin.login.store'), ['login' => 'owner', 'password' => 'new-secret-password'])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
    }
}
