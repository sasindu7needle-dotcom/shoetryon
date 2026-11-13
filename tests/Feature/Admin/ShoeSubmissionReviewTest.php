<?php

namespace Tests\Feature\Admin;

use App\Http\Requests\Admin\ReviewShoeSubmissionRequest;
use App\Models\Shoe;
use App\Models\ShoeFitRound;
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

    public function test_dashboard_lists_shoes_waiting_on_the_admin_with_the_model_review_first(): void
    {
        ShoeSubmission::factory()->readyForReview()->create(['name' => 'Phone Test Pump']);
        ShoeSubmission::factory()->modelReady()->create(['name' => 'Fresh Model Sneaker']);
        ShoeSubmission::factory()->failed()->create(['name' => 'Broken Boot']);
        ShoeSubmission::factory()->create(['name' => 'Still Cooking Clog']);

        $response = $this->actingAs($this->admin)->withoutVite()->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Needs your review');
        $response->assertSeeInOrder(['Fresh Model Sneaker', 'Review model', 'Phone Test Pump']);
        $response->assertDontSee('Broken Boot');
        $response->assertDontSee('Still Cooking Clog');
    }

    public function test_dashboard_hides_the_review_section_when_nothing_is_waiting(): void
    {
        ShoeSubmission::factory()->failed()->create();

        $this->actingAs($this->admin)->withoutVite()->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('Needs your review');
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
            ->assertSee('Approve and add to library')
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
            ->assertDontSee('Approve and add to library')
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
        $this->assertSame(ShoeSubmissionStatus::ReadyForReview, $submission->status);
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
            ->assertDontSee('Approve and add to library');
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
            ->assertDontSee('Approve and add to library');
    }

    public function test_the_effect_adds_the_shoe_to_the_library_hidden(): void
    {
        $submission = $this->submissionWithFiles('modelApproved');

        $response = $this->actingAs($this->admin)->post(route('admin.shoe-submissions.library.store', $submission), ['effect' => $this->effect()]);

        $shoe = Shoe::query()->where('slug', 'leather-loafer')->sole();
        $response->assertRedirect(route('admin.shoe-submissions.show', $submission));
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

    /**
     * A submission whose shoe is in the library, hidden, waiting for the live try-on review.
     */
    private function submissionInLibrary(): ShoeSubmission
    {
        $submission = ShoeSubmission::factory()->modelApproved()->readyForReview()->create(['name' => 'Leather Loafer', 'slug' => 'leather-loafer']);
        Shoe::factory()->hidden()->create(['slug' => 'leather-loafer', 'name' => 'Leather Loafer']);

        return $submission;
    }

    public function test_a_shoe_in_the_library_shows_the_live_try_on_review(): void
    {
        config(['services.n8n.reoptimize_url' => 'https://n8n.example.test/webhook/reoptimize', 'services.n8n.shopar_key' => 'key']);
        $submission = $this->submissionInLibrary();
        $shoe = Shoe::query()->where('slug', 'leather-loafer')->sole();

        $this->actingAs($this->admin)->withoutVite()->get(route('admin.shoe-submissions.show', $submission))
            ->assertOk()
            ->assertSee('Live try-on review')
            ->assertSee(route('tryon', ['preview' => 'leather-loafer']), false)
            ->assertSee(route('admin.shoe-submissions.publish.store', $submission), false)
            ->assertSee(route('admin.shoes.fit-rounds.store', $shoe), false)
            ->assertDontSee('Add to library');
    }

    public function test_the_try_on_review_shows_when_claude_is_still_working(): void
    {
        $submission = $this->submissionInLibrary();
        ShoeFitRound::factory()->create(['shoe_id' => Shoe::query()->where('slug', 'leather-loafer')->value('id')]);

        $this->actingAs($this->admin)->withoutVite()->get(route('admin.shoe-submissions.show', $submission))
            ->assertOk()
            ->assertSee('Claude is working out a new fit');
    }

    public function test_approving_the_try_on_publishes_the_shoe(): void
    {
        $submission = $this->submissionInLibrary();

        $this->actingAs($this->admin)->post(route('admin.shoe-submissions.publish.store', $submission))
            ->assertRedirect(route('admin.shoe-submissions.show', $submission));

        $this->assertTrue(Shoe::query()->where('slug', 'leather-loafer')->sole()->is_visible);
        $this->assertSame(ShoeSubmissionStatus::Published, $submission->refresh()->status);
    }

    public function test_a_shoe_cannot_be_published_while_claude_is_changing_its_fit(): void
    {
        $submission = $this->submissionInLibrary();
        $shoe = Shoe::query()->where('slug', 'leather-loafer')->sole();
        ShoeFitRound::factory()->create(['shoe_id' => $shoe->id]);

        $this->actingAs($this->admin)->from(route('admin.shoe-submissions.show', $submission))
            ->post(route('admin.shoe-submissions.publish.store', $submission))
            ->assertRedirect(route('admin.shoe-submissions.show', $submission))
            ->assertSessionHas('error');

        $this->assertFalse($shoe->refresh()->is_visible);
        $this->assertSame(ShoeSubmissionStatus::ReadyForReview, $submission->refresh()->status);
    }

    public function test_only_a_shoe_waiting_for_its_try_on_can_be_published(): void
    {
        $submission = $this->submissionWithFiles('modelApproved');

        $this->actingAs($this->admin)->post(route('admin.shoe-submissions.publish.store', $submission))
            ->assertConflict();
    }

    public function test_the_review_offers_a_preview_on_the_admins_feet(): void
    {
        $submission = $this->submissionWithFiles();

        $this->actingAs($this->admin)->withoutVite()->get(route('admin.shoe-submissions.show', $submission))
            ->assertOk()
            ->assertSee('Preview on my feet')
            ->assertSee(route('admin.shoe-submissions.preview', $submission), false);
    }

    public function test_the_feet_preview_loads_the_glb_without_a_deepar_effect(): void
    {
        config(['services.deepar.license_key' => 'test-license']);
        $submission = $this->submissionWithFiles();

        $this->actingAs($this->admin)->withoutVite()->get(route('admin.shoe-submissions.preview', $submission))
            ->assertOk()
            ->assertSee('on your feet')
            ->assertViewHas('preview', fn (array $preview) => $preview['modelUrl'] === route('admin.shoe-submissions.files.show', [$submission, 'model'])
                && $preview['fit'] === Shoe::FEET_DEFAULT_FIT
                && $preview['saveUrl'] === route('admin.shoe-submissions.preview.update', $submission))
            ->assertSee('Done, review it');
    }

    public function test_approving_adds_the_shoe_to_the_library_worn_as_its_glb(): void
    {
        $submission = $this->submissionWithFiles();
        $previewFit = ['scale' => 1.1, 'rotation' => [0.0, 90.0, 0.0], 'position' => [0.0, -1.5, 2.0]];
        $submission->update(['preview_fit' => ['fit' => $previewFit, 'leg_mask' => true]]);

        $this->actingAs($this->admin)->post(route('admin.shoe-submissions.review.store', $submission), [
            'decision' => 'approve',
            'checks' => array_keys(ReviewShoeSubmissionRequest::CHECKS),
        ])->assertRedirect(route('admin.shoe-submissions.show', $submission));

        $shoe = Shoe::query()->where('slug', 'leather-loafer')->sole();
        $this->assertSame('Leather Loafer', $shoe->name);
        $this->assertSame('models/generated/leather-loafer.glb', $shoe->glb);
        $this->assertSame('models/generated/leather-loafer.jpg', $shoe->thumb);
        $this->assertNull($shoe->effect);
        $this->assertFalse($shoe->usesEffect());
        $this->assertEquals($previewFit, $shoe->fit);
        $this->assertTrue($shoe->leg_mask);
        $this->assertFalse($shoe->is_visible);

        $this->actingAs($this->admin)->withoutVite()->get(route('admin.shoe-submissions.show', $submission))
            ->assertSee('Live try-on review')
            ->assertSee(route('tryon', ['preview' => 'leather-loafer']), false);
    }

    public function test_approving_without_a_preview_fit_starts_from_the_default_fit_with_the_feet_inside(): void
    {
        $submission = $this->submissionWithFiles();

        $this->actingAs($this->admin)->post(route('admin.shoe-submissions.review.store', $submission), [
            'decision' => 'approve',
            'checks' => array_keys(ReviewShoeSubmissionRequest::CHECKS),
        ]);

        $shoe = Shoe::query()->where('slug', 'leather-loafer')->sole();
        $this->assertEquals(Shoe::FEET_DEFAULT_FIT, $shoe->fit);
        $this->assertTrue($shoe->leg_mask);
    }

    public function test_approving_a_shoe_whose_id_is_already_in_the_library_is_refused(): void
    {
        $submission = $this->submissionWithFiles();
        Shoe::factory()->create(['slug' => 'leather-loafer']);

        $this->actingAs($this->admin)->post(route('admin.shoe-submissions.review.store', $submission), [
            'decision' => 'approve',
            'checks' => array_keys(ReviewShoeSubmissionRequest::CHECKS),
        ])->assertSessionHas('error');

        $this->assertSame(ShoeSubmissionStatus::ModelReady, $submission->refresh()->status);
        $this->assertSame(1, Shoe::query()->count());
    }

    public function test_the_feet_preview_saves_the_fit_the_admin_sets(): void
    {
        $submission = $this->submissionWithFiles();

        $this->actingAs($this->admin)->putJson(route('admin.shoe-submissions.preview.update', $submission), [
            'fit' => ['scale' => 0.95, 'rotation' => [0, 90, 180], 'position' => [0, 1.5, -0.5]],
            'leg_mask' => true,
        ])->assertOk();

        $this->assertEquals([
            'fit' => ['scale' => 0.95, 'rotation' => [0.0, 90.0, 180.0], 'position' => [0.0, 1.5, -0.5]],
            'leg_mask' => true,
        ], $submission->refresh()->preview_fit);
    }

    public function test_the_feet_preview_rejects_a_broken_fit(): void
    {
        $submission = $this->submissionWithFiles();

        $this->actingAs($this->admin)->putJson(route('admin.shoe-submissions.preview.update', $submission), [
            'fit' => ['scale' => 0, 'rotation' => [0, 90], 'position' => [0, 0, 500]],
            'leg_mask' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors(['fit.scale', 'fit.rotation', 'fit.position.2']);

        $this->assertNull($submission->refresh()->preview_fit);
    }

    public function test_the_feet_preview_fit_cannot_change_after_the_review(): void
    {
        $submission = $this->submissionWithFiles('modelApproved');

        $this->actingAs($this->admin)->putJson(route('admin.shoe-submissions.preview.update', $submission), [
            'fit' => ['scale' => 1, 'rotation' => [0, 0, 0], 'position' => [0, 0, 0]],
            'leg_mask' => false,
        ])->assertConflict();
    }

    public function test_the_feet_preview_needs_the_3d_model_file(): void
    {
        $submission = ShoeSubmission::factory()->modelReady()->create();

        $this->actingAs($this->admin)->get(route('admin.shoe-submissions.preview', $submission))->assertNotFound();
    }

    public function test_users_who_are_not_admins_cannot_review_shoes(): void
    {
        $submission = $this->submissionWithFiles();
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.shoe-submissions.show', $submission))->assertForbidden();
        $this->actingAs($user)->get(route('admin.shoe-submissions.files.show', [$submission, 'model']))->assertForbidden();
        $this->actingAs($user)->get(route('admin.shoe-submissions.preview', $submission))->assertForbidden();
        $this->actingAs($user)->putJson(route('admin.shoe-submissions.preview.update', $submission), [])->assertForbidden();
        $this->actingAs($user)->post(route('admin.shoe-submissions.review.store', $submission), ['decision' => 'reject', 'note' => 'No.'])->assertForbidden();
        $this->actingAs($user)->post(route('admin.shoe-submissions.library.store', $submission), ['effect' => $this->effect()])->assertForbidden();
        $this->actingAs($user)->post(route('admin.shoe-submissions.publish.store', $submission))->assertForbidden();
    }
}
