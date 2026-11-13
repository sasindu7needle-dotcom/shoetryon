<?php

namespace Tests\Feature\Admin;

use App\Http\Requests\Admin\ReviewShoeSubmissionRequest;
use App\Http\Requests\Admin\StoreSubmissionFitRoundRequest;
use App\Models\Shoe;
use App\Models\ShoeFitRound;
use App\Models\ShoeSubmission;
use App\Models\User;
use App\ShoeFitRoundStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FeetPreviewPhotoTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_URL = 'https://n8n.example.test/webhook/shopar-fit-reoptimize';

    private const KEY = 'shared-test-key';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('shoe_assets');
        $this->admin = User::factory()->admin()->create();
        config([
            'services.n8n.reoptimize_url' => self::WEBHOOK_URL,
            'services.n8n.shopar_key' => self::KEY,
        ]);
    }

    private function submissionWithModel(): ShoeSubmission
    {
        $submission = ShoeSubmission::factory()->modelReady()->create(['name' => 'Beach Sandal', 'slug' => 'beach-sandal']);
        Storage::disk('shoe_assets')->put($submission->glb_path, 'glTF-model-bytes');

        return $submission;
    }

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('try-on.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));
    }

    public function test_a_photo_from_the_feet_preview_is_sent_to_claude_with_the_preview_fit(): void
    {
        Http::fake([self::WEBHOOK_URL => Http::response('Accepted')]);
        $submission = $this->submissionWithModel();

        $response = $this->actingAs($this->admin)->postJson(route('admin.shoe-submissions.fit-rounds.store', $submission), [
            'photo' => $this->photo(),
            'comment' => 'My heel sticks out of the back.',
        ]);

        $round = ShoeFitRound::query()->sole();
        $response->assertCreated()->assertJsonPath('round.id', $round->id)->assertJsonPath('round.status', 'processing');
        $this->assertTrue($round->submission->is($submission));
        $this->assertNull($round->shoe_id);
        $this->assertEquals(Shoe::FEET_DEFAULT_FIT, $round->previous_fit);

        Http::assertSent(fn (Request $request) => $request->hasHeader('X-ShopAR-Key', self::KEY)
            && $request['round_id'] === $round->id
            && $request['comment'] === 'My heel sticks out of the back.'
            && $request['renderer'] === 'glb_on_feet'
            && $request['shoe']['id'] === 'beach-sandal'
            && $request['shoe']['fit'] == Shoe::FEET_DEFAULT_FIT
            && $request['screenshot']['media_type'] === 'image/png'
            && $request['result_url'] === route('n8n.fit-rounds.result', $round));
    }

    public function test_the_comment_is_optional(): void
    {
        Http::fake([self::WEBHOOK_URL => Http::response('Accepted')]);
        $submission = $this->submissionWithModel();

        $this->actingAs($this->admin)->postJson(route('admin.shoe-submissions.fit-rounds.store', $submission), ['photo' => $this->photo()])
            ->assertCreated();

        Http::assertSent(fn (Request $request) => $request['comment'] === StoreSubmissionFitRoundRequest::DEFAULT_COMMENT);
    }

    public function test_a_photo_is_required(): void
    {
        Http::fake();
        $submission = $this->submissionWithModel();

        $this->actingAs($this->admin)->postJson(route('admin.shoe-submissions.fit-rounds.store', $submission), ['comment' => 'Too big.'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('photo');
        Http::assertNothingSent();
    }

    public function test_only_one_photo_is_worked_on_at_a_time(): void
    {
        Http::fake();
        $submission = $this->submissionWithModel();
        ShoeFitRound::factory()->create(['shoe_id' => null, 'shoe_submission_id' => $submission->id]);

        $this->actingAs($this->admin)->postJson(route('admin.shoe-submissions.fit-rounds.store', $submission), ['photo' => $this->photo()])
            ->assertConflict();
        Http::assertNothingSent();
    }

    public function test_a_shoe_that_was_already_reviewed_cannot_be_sent_from_the_preview(): void
    {
        Http::fake();
        $submission = ShoeSubmission::factory()->readyForReview()->create();

        $this->actingAs($this->admin)->postJson(route('admin.shoe-submissions.fit-rounds.store', $submission), ['photo' => $this->photo()])
            ->assertConflict();
        Http::assertNothingSent();
    }

    public function test_a_refused_request_is_recorded_as_failed(): void
    {
        Http::fake([self::WEBHOOK_URL => Http::response('Nope', 404)]);
        $submission = $this->submissionWithModel();

        $this->actingAs($this->admin)->postJson(route('admin.shoe-submissions.fit-rounds.store', $submission), ['photo' => $this->photo()])
            ->assertStatus(502)
            ->assertJsonPath('round.status', 'failed');

        $this->assertSame(ShoeFitRoundStatus::Failed, ShoeFitRound::query()->sole()->status);
    }

    public function test_claudes_fit_becomes_the_preview_fit_and_the_open_preview_can_read_it(): void
    {
        $submission = $this->submissionWithModel();
        $submission->update(['preview_fit' => ['fit' => Shoe::FEET_DEFAULT_FIT, 'leg_mask' => true]]);
        $round = ShoeFitRound::factory()->create(['shoe_id' => null, 'shoe_submission_id' => $submission->id]);
        $newFit = ['scale' => 1.05, 'rotation' => [0, 0, 0], 'position' => [0, -1, 1.5]];

        $this->withHeader('X-ShopAR-Key', self::KEY)
            ->postJson(route('n8n.fit-rounds.result', $round), ['changed' => true, 'note' => 'Moved the shoe forward.', 'fit' => $newFit])
            ->assertOk();

        $this->assertEquals(['fit' => $newFit, 'leg_mask' => true], $submission->refresh()->preview_fit);

        $this->actingAs($this->admin)->getJson(route('admin.shoe-submissions.fit-rounds.latest', $submission))
            ->assertOk()
            ->assertJsonPath('round.status', 'applied')
            ->assertJsonPath('round.message', 'Moved the shoe forward.')
            ->assertJsonPath('round.fit.scale', 1.05);

        $this->actingAs($this->admin)->withoutVite()->get(route('admin.shoe-submissions.preview', $submission))
            ->assertOk()
            ->assertViewHas('preview', fn (array $preview) => $preview['isClaudeFit'] === true && $preview['fit'] == $newFit);
    }

    public function test_the_preview_offers_sending_photos_only_when_the_webhook_is_set(): void
    {
        config(['services.deepar.license_key' => 'test-license']);
        $submission = $this->submissionWithModel();

        $this->actingAs($this->admin)->withoutVite()->get(route('admin.shoe-submissions.preview', $submission))
            ->assertOk()
            ->assertSee('Send to Claude to optimize the fit')
            ->assertViewHas('preview', fn (array $preview) => $preview['claudeUrl'] === route('admin.shoe-submissions.fit-rounds.store', $submission)
                && $preview['legMask'] === true
                && $preview['isClaudeFit'] === false);

        config(['services.n8n.reoptimize_url' => null]);

        $this->actingAs($this->admin)->withoutVite()->get(route('admin.shoe-submissions.preview', $submission))
            ->assertOk()
            ->assertDontSee('Send to Claude to optimize the fit')
            ->assertViewHas('preview', fn (array $preview) => $preview['claudeUrl'] === null);
    }

    public function test_the_model_cannot_be_approved_while_claude_is_changing_its_preview_fit(): void
    {
        $submission = $this->submissionWithModel();
        ShoeFitRound::factory()->create(['shoe_id' => null, 'shoe_submission_id' => $submission->id]);

        $this->actingAs($this->admin)->post(route('admin.shoe-submissions.review.store', $submission), [
            'decision' => 'approve',
            'checks' => array_keys(ReviewShoeSubmissionRequest::CHECKS),
        ])->assertSessionHas('error');

        $this->assertFalse(Shoe::query()->where('slug', 'beach-sandal')->exists());
    }
}
