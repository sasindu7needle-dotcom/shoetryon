<?php

namespace Database\Factories;

use App\Models\Shoe;
use App\Models\ShoeFitRound;
use App\ShoeFitRoundStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShoeFitRound>
 */
class ShoeFitRoundFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shoe_id' => Shoe::factory(),
            'comment' => 'The heel sticks out behind the foot.',
            'status' => ShoeFitRoundStatus::Processing,
            'previous_fit' => ['scale' => 90, 'rotation' => [0, -90, 0], 'position' => [0.75, -1.8, -4.85]],
        ];
    }

    /**
     * A round whose new fit was saved to the shoe.
     */
    public function applied(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ShoeFitRoundStatus::Applied,
            'new_fit' => ['scale' => 90, 'rotation' => [0, -90, 0], 'position' => [0.75, -1.8, -4.35]],
            'message' => 'Moved the shoe 0.5 forward.',
        ]);
    }
}
