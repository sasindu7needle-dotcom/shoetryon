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
     * A submission whose 3D model n8n sent back, waiting for the admin's review.
     */
    public function modelReady(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ShoeSubmissionStatus::ModelReady,
            'photo_path' => fn (array $attributes) => 'models/generated/'.$attributes['slug'].'.jpg',
            'glb_path' => fn (array $attributes) => 'models/generated/'.$attributes['slug'].'.glb',
        ]);
    }

    /**
     * A submission whose 3D model the admin approved, waiting for its DeepAR effect.
     */
    public function modelApproved(): static
    {
        return $this->modelReady()->state(fn (array $attributes) => [
            'status' => ShoeSubmissionStatus::ModelApproved,
            'reviewed_at' => now(),
            'review_checks' => ['shape', 'sole', 'colours', 'clean'],
        ]);
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
