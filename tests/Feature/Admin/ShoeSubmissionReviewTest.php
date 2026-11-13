<?php

namespace Tests\Feature\Admin;

use App\Http\Requests\Admin\ReviewShoeSubmissionRequest;
use App\Models\Shoe;
use App\Models\ShoeSubmission;
use App\Models\User;
use App\ShoeSubmissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ShoeSubmissionReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('shoe_assets');
        $this->admin = User::factory()->admin()->create();
    }

    /**
     * A submission named Leather Loafer whose photo and GLB are on disk.
     *
     * @param  'modelReady'|'modelApproved'  $state
     */
    private function submissionWithFiles(string $state = 'modelReady'): ShoeSubmission
    {
        $submission = ShoeSubmission::factory()->{$state}()->create(['name' => 'Leather Loafer', 'slug' => 'leather-loafer']);

        Storage::disk('shoe_assets')->put($submission->photo_path, 'jpeg-bytes');
        Storage::disk('shoe_assets')->put($submission->glb_path, 'glTF-model-bytes');

        return $submission;
    }

    private function effect(string $name = 'leather-loafer.deepar'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, 'deepar-effect-bytes');
    }

    public function test_page_shows_the_photo_the_3d_model_and_the_review_form(): void
    {
        $submission = $this->submissionWithFiles();

        $this->actingAs($this->admin)->withoutVite()->get(route('admin.shoe-submissions.show', $submission))
            ->assertOk()
            ->assertSee('src="'.route('admin.shoe-submissions.files.show', [$submission, 'photo']).'"', false)
            ->assertSee('data-model-preview="'.route('admin.shoe-submissions.files.show', [$submission, 'model']).'"', false)
            ->assertSee('Download GLB')
            ->assertSee('Review the 3D model')
            ->assertSee('Approve model')
            ->assertDontSee('Add to library');
    }

    public function test_page_explains_when_the_files_are_gone_from_the_server(): void
    {
        $submission = ShoeSubmission::factory()->modelReady()->create();

        $this->actingAs($this->admin)->withoutVite()->get(route('admin.shoe-submissions.show', $submission))
            ->assertOk()
            ->assertSee('The front photo is no longer on the server.')
            ->assertSee('The 3D model file is no longer on the server.')
            ->assertDontSee('data-model-preview', false);
    }

    public function test_page_says_the_model_is_still_being_made(): void
    {
        $submission = ShoeSubmission::factory()->create();

        $this->actingAs($this->admin)->withoutVite()->get(route('admin.shoe-submissions.show', $submission))
            ->assertOk()
            ->assertSee('Tripo is making the 3D model')
            ->assertDontSee('Approve model')
            ->assertDontSee('Add to library');
    }

    public function test_the_submission_list_links_to_each_shoe(): void
    {
        $submission = $this->submissionWithFiles();

        $this->actingAs($this->admin)->withoutVite()->get(route('admin.shoe-submissions.create'))
            ->assertSee(route('admin.shoe-submissions.show', $submission))
            ->assertSee('Model to review');
    }

    public function test_the_photo_and_model_are_served_to_admins(): void
    {
        $submission = $this->submissionWithFiles();

        $this->actingAs($this->admin)->get(route('admin.shoe-submissions.files.show', [$submission, 'model']))
            ->assertOk()
            ->assertHeader('Content-Type', 'model/gltf-binary');

        $this->actingAs($this->admin)->get(route('admin.shoe-submissions.files.show', [$submission, 'photo']))
            ->assertOk();

        $this->actingAs($this->admin)->get(route('admin.shoe-submissions.files.show', [$submission, 'model']).'?download=1')
            ->assertDownload('leather-loafer.glb');
    }

    public function test_a_missing_file_is_not_found(): void
    {
        $submission = ShoeSubmission::factory()->modelReady()->create();

        $this->actingAs($this->admin)->get(route('admin.shoe-submissions.files.show', [$submission, 'model']))->assertNotFound();
    }

    public function test_the_admin_approves_a_model_that_passes_every_check(): void
    {
        $submission = $this->submissionWithFiles();

        $this->actingAs($this->admin)->post(route('admin.shoe-submissions.review.store', $submission), [
            'decision' => 'approve',
            'checks' => array_keys(ReviewShoeSubmissionRequest::CHECKS),
            'note' => 'Looks good.',
        ])->assertRedirect(route('admin.shoe-submissions.show', $submission));

        $submission->refresh();
        $this->assertSame(ShoeSubmissionStatus::ModelApproved, $submission->status);
        $this->assertTrue($submission->reviewer->is($this->admin));
        $this->assertNotNull($submission->reviewed_at);
        $this->assertSame(array_keys(ReviewShoeSubmissionRequest::CHECKS), $submission->review_checks);
        $this->assertSame('Looks good.', $submission->review_note);
    }

    public function test_approving_needs_every_check(): void
    {
        $submission = $this->submissionWithFiles();

        $this->actingAs($this->admin)->post(route('admin.shoe-submissions.review.store', $submission), [
            'decision' => 'approve',
            'checks' => ['shape', 'sole'],
        ])->assertSessionHasErrors('checks');

        $this->assertSame(ShoeSubmissionStatus::ModelReady, $submission->refresh()->status);
    }

    public function test_the_admin_rejects_a_model_with_a_reason_and_can_send_the_shoe_again(): void
    {
        $submission = $this->submissionWithFiles();

        $this->actingAs($this->admin)->post(route('admin.shoe-submissions.review.store', $submission), [
            'decision' => 'reject',
            'checks' => ['shape'],
            'note' => 'The sole is hollow.',
        ])->assertRedirect(route('admin.shoe-submissions.show', $submission));

        $submission->refresh();
        $this->assertSame(ShoeSubmissionStatus::Rejected, $submission->status);
        $this->assertSame('The sole is hollow.', $submission->review_note);
        $this->assertNotContains(ShoeSubmissionStatus::Rejected, ShoeSubmissionStatus::claimingId());

        $this->actingAs($this->admin)->withoutVite()->get(route('admin.shoe-submissions.show', $submission))
            ->assertSee('The sole is hollow.')
            ->assertSee('Send it again')
            ->assertDontSee('Approve model');
    }

    public function test_rejecting_needs_a_reason(): void
    {
        $submission = $this->submissionWithFiles();

        $this->actingAs($this->admin)->post(route('admin.shoe-submissions.review.store', $submission), ['decision' => 'reject'])
            ->assertSessionHasErrors('note');

        $this->assertSame(ShoeSubmissionStatus::ModelReady, $submission->refresh()->status);
    }

    public function test_a_model_can_only_be_reviewed_once(): void
    {
        $submission = $this->submissionWithFiles('modelApproved');

        $this->actingAs($this->admin)->post(route('admin.shoe-submissions.review.store', $submission), ['decision' => 'reject', 'note' => 'Changed my mind.'])
            ->assertConflict();
    }

    public function test_an_approved_model_shows_the_effect_form(): void
    {
        $submission = $this->submissionWithFiles('modelApproved');

        $this->actingAs($this->admin)->withoutVite()->get(route('admin.shoe-submissions.show', $submission))
            ->assertOk()
            ->assertSee('Add to library')
            ->assertDontSee('Approve model');
    }

    public function test_the_effect_adds_the_shoe_to_the_library_hidden(): void
    {
        $submission = $this->submissionWithFiles('modelApproved');

        $response = $this->actingAs($this->admin)->post(route('admin.shoe-submissions.library.store', $submission), ['effect' => $this->effect()]);

        $shoe = Shoe::query()->where('slug', 'leather-loafer')->sole();
        $response->assertRedirect(route('admin.shoes.edit', $shoe));
        $this->assertSame('Leather Loafer', $shoe->name);
        $this->assertSame('models/generated/leather-loafer.glb', $shoe->glb);
        $this->assertSame('models/generated/leather-loafer.jpg', $shoe->thumb);
        $this->assertSame('effects/generated/leather-loafer.deepar', $shoe->effect);
        $this->assertNull($shoe->fit);
        $this->assertFalse($shoe->is_visible);
        $this->assertSame(ShoeSubmissionStatus::ReadyForReview, $submission->refresh()->status);
        Storage::disk('shoe_assets')->assertExists('effects/generated/leather-loafer.deepar');
    }

    public function test_only_a_deepar_file_is_accepted(): void
    {
        $submission = $this->submissionWithFiles('modelApproved');

        $this->actingAs($this->admin)->post(route('admin.shoe-submissions.library.store', $submission), ['effect' => $this->effect('shoe.zip')])
            ->assertSessionHasErrors('effect');

        $this->assertDatabaseEmpty('shoes');
    }

    public function test_a_model_that_is_not_approved_cannot_be_added(): void
    {
        foreach ([ShoeSubmission::factory()->create(), $this->submissionWithFiles()] as $submission) {
            $this->actingAs($this->admin)->post(route('admin.shoe-submissions.library.store', $submission), ['effect' => $this->effect()])
                ->assertConflict();
        }

        $this->assertDatabaseEmpty('shoes');
    }

    public function test_users_who_are_not_admins_cannot_review_shoes(): void
    {
        $submission = $this->submissionWithFiles();
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.shoe-submissions.show', $submission))->assertForbidden();
        $this->actingAs($user)->get(route('admin.shoe-submissions.files.show', [$submission, 'model']))->assertForbidden();
        $this->actingAs($user)->post(route('admin.shoe-submissions.review.store', $submission), ['decision' => 'reject', 'note' => 'No.'])->assertForbidden();
        $this->actingAs($user)->post(route('admin.shoe-submissions.library.store', $submission), ['effect' => $this->effect()])->assertForbidden();
    }
}
