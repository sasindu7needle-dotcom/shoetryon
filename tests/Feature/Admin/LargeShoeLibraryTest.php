<?php

namespace Tests\Feature\Admin;

use App\Models\Shoe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LargeShoeLibraryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_library_shows_ten_shoes_a_page_with_page_numbers(): void
    {
        Shoe::factory()->count(23)->create();

        $response = $this->actingAs($this->admin)->withoutVite()->get(route('admin.shoes.index'));

        $response->assertOk();
        $response->assertViewHas('shoes', fn ($shoes) => $shoes->count() === 10 && $shoes->lastPage() === 3);
        $response->assertSeeInOrder(['Showing', '1–10', 'of', '23', 'shoes'], false);
        $response->assertSee('aria-label="Page 3"', false);
    }

    public function test_later_pages_continue_the_library_order(): void
    {
        $shoes = Shoe::factory()->count(12)->create();

        $response = $this->actingAs($this->admin)->withoutVite()->get(route('admin.shoes.index', ['page' => 2]));

        $response->assertViewHas('shoes', fn ($page) => $page->pluck('id')->all() === [$shoes[10]->id, $shoes[11]->id]);
        $response->assertSeeInOrder(['Showing', '11–12', 'of', '12'], false);
    }

    public function test_page_size_can_be_changed_to_an_allowed_value(): void
    {
        Shoe::factory()->count(30)->create();

        $this->actingAs($this->admin)->withoutVite()->get(route('admin.shoes.index', ['per_page' => 25]))
            ->assertViewHas('shoes', fn ($shoes) => $shoes->count() === 25);

        $this->actingAs($this->admin)->get(route('admin.shoes.index', ['per_page' => 5000]))
            ->assertSessionHasErrors('per_page');
    }

    public function test_page_links_keep_the_filter_search_and_page_size(): void
    {
        Shoe::factory()->count(30)->hidden()->create(['name' => fn () => 'Boot '.fake()->unique()->numberBetween(1, 999)]);

        $response = $this->actingAs($this->admin)->withoutVite()->get(route('admin.shoes.index', ['status' => 'hidden', 'search' => 'Boot', 'per_page' => 25]));

        $response->assertSee(e(route('admin.shoes.index', ['status' => 'hidden', 'search' => 'Boot', 'per_page' => 25, 'page' => 2])), false);
    }

    public function test_a_page_past_the_end_goes_to_the_last_page(): void
    {
        Shoe::factory()->count(11)->create();

        $this->actingAs($this->admin)->get(route('admin.shoes.index', ['page' => 5]))
            ->assertRedirect(route('admin.shoes.index', ['page' => 2]));
    }

    public function test_library_can_be_filtered_to_visible_or_hidden_shoes(): void
    {
        Shoe::factory()->create(['name' => 'Visible Vans']);
        Shoe::factory()->hidden()->create(['name' => 'Hidden Heel']);

        $this->actingAs($this->admin)->withoutVite()->get(route('admin.shoes.index', ['status' => 'hidden']))
            ->assertSee('Hidden Heel')->assertDontSee('Visible Vans')
            ->assertViewHas('counts', ['all' => 2, 'visible' => 1, 'hidden' => 1]);

        $this->actingAs($this->admin)->withoutVite()->get(route('admin.shoes.index', ['status' => 'visible']))
            ->assertSee('Visible Vans')->assertDontSee('Hidden Heel');
    }

    public function test_library_can_be_searched_by_name_or_slug(): void
    {
        Shoe::factory()->create(['name' => 'Trail Runner', 'slug' => 'trail-runner']);
        Shoe::factory()->create(['name' => 'Office Loafer', 'slug' => 'loafer-2026']);

        $this->actingAs($this->admin)->withoutVite()->get(route('admin.shoes.index', ['search' => 'trail']))
            ->assertSee('Trail Runner')->assertDontSee('Office Loafer');

        $this->actingAs($this->admin)->withoutVite()->get(route('admin.shoes.index', ['search' => '2026']))
            ->assertSee('Office Loafer')->assertDontSee('Trail Runner');
    }

    public function test_search_treats_wildcards_as_plain_text(): void
    {
        Shoe::factory()->create(['name' => 'Plain Pump']);

        $this->actingAs($this->admin)->withoutVite()->get(route('admin.shoes.index', ['search' => '%']))
            ->assertDontSee('Plain Pump')
            ->assertSee('No shoes match');
    }

    public function test_reordering_is_turned_off_while_filtering(): void
    {
        Shoe::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)->withoutVite()->get(route('admin.shoes.index', ['status' => 'visible']));

        $response->assertSee('Reordering is off while a filter or search is on.');
        $response->assertSee('title="Show all shoes to reorder"', false);
    }

    public function test_hidden_shoes_offer_unhide_and_visible_shoes_offer_hide(): void
    {
        $visible = Shoe::factory()->create(['name' => 'Visible Vans']);
        $hidden = Shoe::factory()->hidden()->create(['name' => 'Hidden Heel']);

        $response = $this->actingAs($this->admin)->withoutVite()->get(route('admin.shoes.index'));

        $response->assertSee('aria-label="Hide Visible Vans"', false);
        $response->assertSee('aria-label="Unhide Hidden Heel"', false);
        $response->assertSee(route('admin.shoes.visibility.destroy', $visible));
        $response->assertSee(route('admin.shoes.visibility.store', $hidden));
    }

    public function test_hiding_returns_to_the_same_library_page(): void
    {
        $shoe = Shoe::factory()->create();
        $libraryPage = route('admin.shoes.index', ['page' => 2, 'status' => 'visible']);

        $this->actingAs($this->admin)->from($libraryPage)->delete(route('admin.shoes.visibility.destroy', $shoe))
            ->assertRedirect($libraryPage);
    }

    public function test_moving_a_shoe_swaps_only_it_and_its_neighbour(): void
    {
        $shoes = Shoe::factory()->count(5)->create();
        $untouchedTimestamps = collect([$shoes[0], $shoes[1], $shoes[4]])->mapWithKeys(fn (Shoe $shoe) => [$shoe->id => $shoe->updated_at]);
        $this->travel(1)->minute();

        $this->actingAs($this->admin)->patch(route('admin.shoes.position.update', $shoes[3]), ['direction' => 'up']);

        $this->assertSame(3, $shoes[3]->refresh()->position);
        $this->assertSame(4, $shoes[2]->refresh()->position);
        foreach ($untouchedTimestamps as $id => $updatedAt) {
            $this->assertEquals($updatedAt, Shoe::query()->find($id)->updated_at);
        }
    }

    public function test_moving_works_across_page_boundaries(): void
    {
        $shoes = Shoe::factory()->count(11)->create();

        $this->actingAs($this->admin)->patch(route('admin.shoes.position.update', $shoes[10]), ['direction' => 'up']);

        $this->assertSame(10, $shoes[10]->refresh()->position);
        $this->assertSame(11, $shoes[9]->refresh()->position);
    }

    public function test_moving_renumbers_first_when_two_shoes_share_a_position(): void
    {
        $first = Shoe::factory()->create(['position' => 1]);
        $second = Shoe::factory()->create(['position' => 1]);

        $this->actingAs($this->admin)->patch(route('admin.shoes.position.update', $second), ['direction' => 'up']);

        $this->assertSame([$second->id, $first->id], Shoe::query()->ordered()->pluck('id')->all());
        $this->assertSame([1, 2], Shoe::query()->ordered()->pluck('position')->all());
    }

    public function test_edit_can_put_a_shoe_at_any_position(): void
    {
        $shoes = Shoe::factory()->count(5)->create();
        $moving = $shoes[4];

        $this->actingAs($this->admin)->put(route('admin.shoes.update', $moving), [
            'name' => $moving->name,
            'is_visible' => '1',
            'position' => 2,
            'fit' => ['scale' => 90, 'rotation' => [0, 0, 0], 'position' => [0, 0, 0], 'stretch' => [1, 1, 1]],
        ]);

        $this->assertSame(
            [$shoes[0]->id, $moving->id, $shoes[1]->id, $shoes[2]->id, $shoes[3]->id],
            Shoe::query()->ordered()->pluck('id')->all(),
        );
        $this->assertSame([1, 2, 3, 4, 5], Shoe::query()->ordered()->pluck('position')->all());
    }

    public function test_position_cannot_be_past_the_end_of_the_library(): void
    {
        $shoe = Shoe::factory()->create();

        $this->actingAs($this->admin)->put(route('admin.shoes.update', $shoe), [
            'name' => $shoe->name,
            'position' => 2,
            'fit' => ['scale' => 90, 'rotation' => [0, 0, 0], 'position' => [0, 0, 0], 'stretch' => [1, 1, 1]],
        ])->assertSessionHasErrors('position');
    }

    public function test_saving_returns_to_the_library_page_the_admin_came_from(): void
    {
        $shoe = Shoe::factory()->create();
        $libraryPage = route('admin.shoes.index', ['page' => 3, 'search' => 'run']);

        $this->actingAs($this->admin)->put(route('admin.shoes.update', $shoe), [
            'name' => $shoe->name,
            'position' => 1,
            'return_url' => $libraryPage,
            'fit' => ['scale' => 90, 'rotation' => [0, 0, 0], 'position' => [0, 0, 0], 'stretch' => [1, 1, 1]],
        ])->assertRedirect($libraryPage);
    }

    public function test_saving_ignores_a_return_address_outside_the_library(): void
    {
        $shoe = Shoe::factory()->create();

        $this->actingAs($this->admin)->put(route('admin.shoes.update', $shoe), [
            'name' => $shoe->name,
            'position' => 1,
            'return_url' => 'https://evil.example/phish',
            'fit' => ['scale' => 90, 'rotation' => [0, 0, 0], 'position' => [0, 0, 0], 'stretch' => [1, 1, 1]],
        ])->assertRedirect(route('admin.shoes.index'));
    }

    public function test_deleting_closes_the_gap_in_the_order(): void
    {
        $shoes = Shoe::factory()->count(3)->create();

        $this->actingAs($this->admin)->delete(route('admin.shoes.destroy', $shoes[0]));

        $this->assertSame([1, 2], Shoe::query()->ordered()->pluck('position')->all());
    }

    public function test_dashboard_previews_only_the_first_eight_visible_shoes(): void
    {
        Shoe::factory()->count(12)->create();

        $response = $this->actingAs($this->admin)->withoutVite()->get(route('admin.dashboard'));

        $response->assertViewHas('visibleShoes', fn ($shoes) => $shoes->count() === 8);
        $response->assertSee('The first 8 of 12 visible shoes');
    }
}
