<?php

namespace Database\Factories;

use App\Models\Shoe;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Shoe>
 */
class ShoeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(2, true));
        $slug = Str::slug($name);

        return [
            'slug' => $slug,
            'name' => $name,
            'glb' => "models/samples/{$slug}.glb",
            'thumb' => "models/samples/{$slug}.webp",
            'effect' => "effects/shoes/{$slug}.deepar",
            'fit' => ['scale' => 90, 'rotation' => [0, -90, 0], 'position' => [0.75, -1.8, -4.85]],
            'is_visible' => true,
        ];
    }

    /**
     * A shoe hidden from the public site.
     */
    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => ['is_visible' => false]);
    }

    /**
     * A shoe made from photos: no DeepAR effect, worn as its GLB on the
     * tracked feet. Its GLB is one that ships with the app.
     */
    public function wornAsGlb(): static
    {
        return $this->state(fn (array $attributes) => [
            'glb' => 'models/samples/street-runner.glb',
            'effect' => null,
            'fit' => Shoe::FEET_DEFAULT_FIT,
        ]);
    }

    /**
     * A shoe whose DeepAR effect file ships with the app.
     */
    public function arReady(): static
    {
        return $this->state(fn (array $attributes) => ['effect' => 'effects/shoes/street-runner.deepar']);
    }
}
