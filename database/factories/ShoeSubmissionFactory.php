<?php

namespace Database\Factories;

use App\Models\ShoeSubmission;
use App\ShoeSubmissionStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ShoeSubmission>
 */
class ShoeSubmissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(2, true));

        return [
            'name' => $name,
            'slug' => ShoeSubmission::slugFor($name),
            'shoe_type' => 'low',
            'length_cm' => 27,
            'status' => ShoeSubmissionStatus::Processing,
        ];
    }

    /**
     * A submission whose finished shoe reached the site but is not approved yet.
     */
    public function readyForReview(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ShoeSubmissionStatus::ReadyForReview]);
    }

    /**
     * A submission n8n never received.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ShoeSubmissionStatus::Failed, 'message' => 'n8n could not be reached.']);
    }
}
