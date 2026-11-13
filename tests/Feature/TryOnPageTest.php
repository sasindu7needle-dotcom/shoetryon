<?php

namespace Tests\Feature;

use App\Models\Shoe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TryOnPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_shoes_with_an_effect_are_listed_first(): void
    {
        Shoe::factory()->create(['name' => 'Missing Shoe', 'effect' => 'effects/shoes/does-not-exist.deepar', 'position' => 1]);
        Shoe::factory()->arReady()->create(['name' => 'Canvas Sneaker', 'position' => 2]);

        $response = $this->withoutVite()->get(route('tryon'));

        $response->assertOk();
        $response->assertSeeInOrder(['Canvas Sneaker', 'Missing Shoe']);
        $response->assertSee('data-name="Canvas Sneaker"', false);
    }

    public function test_effect_urls_carry_the_file_version_so_phones_skip_stale_cached_effects(): void
    {
        $shoe = Shoe::factory()->arReady()->create();

        $response = $this->withoutVite()->get(route('tryon'));

        $response->assertOk();
        $response->assertSee('data-effect="'.asset($shoe->effect).'?v='.filemtime(public_path($shoe->effect)).'"', false);
    }

    public function test_fits_are_passed_to_the_page(): void
    {
        $shoe = Shoe::factory()->arReady()->create();

        $response = $this->withoutVite()->get(route('tryon'));

        $response->assertOk();
        $response->assertViewHas('fits', fn ($fits) => $fits[$shoe->slug] === $shoe->fit);
    }

    public function test_shoes_removed_from_the_site_are_not_listed(): void
    {
        Shoe::factory()->create(['name' => 'Listed Loafer']);
        Shoe::factory()->hidden()->create(['name' => 'Hidden Heel']);

        $this->withoutVite()->get(route('tryon'))->assertSee('Listed Loafer')->assertDontSee('Hidden Heel');
        $this->withoutVite()->get(route('home'))->assertSee('Listed Loafer')->assertDontSee('Hidden Heel');
    }

    public function test_home_lists_shoes_in_library_order(): void
    {
        Shoe::factory()->create(['name' => 'Second Shoe', 'position' => 2]);
        Shoe::factory()->create(['name' => 'First Shoe', 'position' => 1]);

        $this->withoutVite()->get(route('home'))->assertOk()->assertSeeInOrder(['First Shoe', 'Second Shoe']);
    }

    public function test_home_renders_when_every_shoe_is_removed_from_the_site(): void
    {
        Shoe::factory()->hidden()->create();

        $this->withoutVite()->get(route('home'))->assertOk();
    }
}
