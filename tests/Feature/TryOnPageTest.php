<?php

namespace Tests\Feature;

use Tests\TestCase;

class TryOnPageTest extends TestCase
{
    public function test_shoes_with_an_effect_are_listed_first(): void
    {
        config(['shoes' => [
            ['id' => 'missing', 'name' => 'Missing Shoe', 'glb' => 'a.glb', 'thumb' => 'a.webp', 'effect' => 'effects/shoes/does-not-exist.deepar'],
            ['id' => 'canvas-sneaker', 'name' => 'Canvas Sneaker', 'glb' => 'b.glb', 'thumb' => 'b.webp', 'effect' => 'effects/shoes/canvas-sneaker.deepar'],
        ]]);

        $response = $this->withoutVite()->get(route('tryon'));

        $response->assertOk();
        $response->assertSeeInOrder(['Canvas Sneaker', 'Missing Shoe']);
        $response->assertSee('data-name="Canvas Sneaker"', false);
    }

    public function test_effect_urls_carry_the_file_version_so_phones_skip_stale_cached_effects(): void
    {
        $effect = 'effects/shoes/street-runner.deepar';

        $response = $this->withoutVite()->get(route('tryon'));

        $response->assertOk();
        $response->assertSee('data-effect="'.asset($effect).'?v='.filemtime(public_path($effect)).'"', false);
    }

    public function test_fits_are_passed_to_the_page(): void
    {
        $response = $this->withoutVite()->get(route('tryon'));

        $response->assertOk();
        $fit = collect(config('shoes'))->firstWhere('id', 'street-runner')['fit'];
        $response->assertViewHas('fits', fn ($fits) => $fits['street-runner'] === $fit);
    }
}
