<?php

namespace Tests\Feature\Admin;

use App\Models\ShoeSubmission;
use App\Models\User;
use App\ShoeSide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ShoeSubmissionSideTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('shoe_assets');
        $this->admin = User::factory()->admin()->create();
    }

    private function submissionAwaitingReview(array $attributes = []): ShoeSubmission
    {
        $submission = ShoeSubmission::factory()->modelReady()->create(['name' => 'Leather Loafer', 'slug' => 'leather-loafer', 'shoe_side' => ShoeSide::Right, ...$attributes]);
        Storage::disk('shoe_assets')->put($submission->glb_path, 'glTF-model-bytes');

        return $submission;
    }

    private function fakeScript(): void
    {
        Process::fake(function () {
            Storage::disk('shoe_assets')->put('effects/generated/leather-loafer.deepar', 'deepar-effect-bytes');
            $fit = ['scale' => 92.593, 'rotation' => [0, -90, 0], 'position' => [0.7, -1.82, -4.6]];

            return Process::result('FIT '.json_encode(['fit' => $fit, 'orientation' => ['shoe' => 'left (mirrored)']]));
        });
    }

    public function test_the_review_page_offers_to_switch_the_side(): void
    {
        $submission = $this->submissionAwaitingReview();

        $this->actingAs($this->admin)->get(route('admin.shoe-submissions.show', $submission))
            ->assertOk()
            ->assertSee('Right shoe')
            ->assertSee('Make it a left shoe');
    }

    public function test_switching_the_side_rebuilds_the_effect_mirrored(): void
    {
        $this->fakeScript();
        $submission = $this->submissionAwaitingReview();

        $response = $this->actingAs($this->admin)->put(route('admin.shoe-submissions.side.update', $submission), ['shoe_side' => 'left']);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Leather Loafer is now a left shoe. Preview it on your feet again.');
        $submission->refresh();
        $this->assertSame(ShoeSide::Left, $submission->shoe_side);
        $this->assertSame('effects/generated/leather-loafer.deepar', $submission->effect_path);
        Process::assertRan(fn (PendingProcess $process) => in_array('--side=left', $process->command, true));
    }

    public function test_the_side_cannot_change_once_the_model_is_reviewed(): void
    {
        Process::fake();
        $submission = ShoeSubmission::factory()->modelApproved()->create();

        $this->actingAs($this->admin)->put(route('admin.shoe-submissions.side.update', $submission), ['shoe_side' => 'left'])
            ->assertConflict();

        Process::assertNothingRan();
    }

    public function test_only_left_or_right_is_accepted(): void
    {
        Process::fake();
        $submission = $this->submissionAwaitingReview();

        $this->actingAs($this->admin)->put(route('admin.shoe-submissions.side.update', $submission), ['shoe_side' => 'both'])
            ->assertSessionHasErrors('shoe_side');

        $this->assertSame(ShoeSide::Right, $submission->refresh()->shoe_side);
        Process::assertNothingRan();
    }

    public function test_users_who_are_not_admins_cannot_switch_the_side(): void
    {
        $submission = $this->submissionAwaitingReview();

        $this->actingAs(User::factory()->create())->put(route('admin.shoe-submissions.side.update', $submission), ['shoe_side' => 'left'])
            ->assertForbidden();
    }
}
