<?php

namespace Tests\Feature\N8n;

use App\Models\ShoeSubmission;
use App\ShoeSubmissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ShoeSubmissionCallbackTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'shared-test-key';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('shoe_assets');
        config(['services.n8n.shopar_key' => self::KEY]);
    }

    private function glb(string $contents = "glTF\x02\x00\x00\x00rest-of-model"): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('model.glb', $contents);
    }

    public function test_n8n_sends_the_glb_and_the_shoe_waits_for_its_effect(): void
    {
        $submission = ShoeSubmission::factory()->create(['name' => 'Leather Loafer', 'slug' => 'leather-loafer']);

        $response = $this->withHeader('X-ShopAR-Key', self::KEY)->post(route('n8n.shoe-submissions.model', 'leather-loafer'), [
            'glb' => $this->glb(),
            'tripo_task_id' => 'task-123',
        ]);

        $response->assertOk()->assertJson(['status' => 'model_ready']);
        $submission->refresh();
        $this->assertSame(ShoeSubmissionStatus::ModelReady, $submission->status);
        $this->assertSame('models/generated/leather-loafer.glb', $submission->glb_path);
        $this->assertSame('task-123', $submission->tripo_task_id);
        Storage::disk('shoe_assets')->assertExists('models/generated/leather-loafer.glb');
    }

    public function test_a_file_that_is_not_a_glb_is_refused(): void
    {
        ShoeSubmission::factory()->create(['slug' => 'leather-loafer']);

        $this->withHeader('X-ShopAR-Key', self::KEY)
            ->postJson(route('n8n.shoe-submissions.model', 'leather-loafer'), ['glb' => $this->glb('<html>not a model</html>')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('glb');

        Storage::disk('shoe_assets')->assertMissing('models/generated/leather-loafer.glb');
    }

    public function test_calls_without_the_right_key_are_refused(): void
    {
        $submission = ShoeSubmission::factory()->create(['slug' => 'leather-loafer']);

        $this->postJson(route('n8n.shoe-submissions.model', 'leather-loafer'), ['glb' => $this->glb()])->assertUnauthorized();
        $this->withHeader('X-ShopAR-Key', 'wrong')->postJson(route('n8n.shoe-submissions.failure', 'leather-loafer'), ['message' => 'x'])->assertUnauthorized();

        $this->assertSame(ShoeSubmissionStatus::Processing, $submission->refresh()->status);
    }

    public function test_callbacks_are_closed_while_no_key_is_configured(): void
    {
        config(['services.n8n.shopar_key' => null]);
        ShoeSubmission::factory()->create(['slug' => 'leather-loafer']);

        $this->withHeader('X-ShopAR-Key', '')->postJson(route('n8n.shoe-submissions.failure', 'leather-loafer'), ['message' => 'x'])
            ->assertServiceUnavailable();
    }

    public function test_only_a_shoe_still_being_made_can_receive_a_model(): void
    {
        ShoeSubmission::factory()->failed()->create(['slug' => 'leather-loafer']);

        $this->withHeader('X-ShopAR-Key', self::KEY)
            ->postJson(route('n8n.shoe-submissions.model', 'leather-loafer'), ['glb' => $this->glb()])
            ->assertNotFound();
    }

    public function test_n8n_reports_why_the_shoe_could_not_be_made(): void
    {
        $submission = ShoeSubmission::factory()->create(['slug' => 'leather-loafer']);

        $this->withHeader('X-ShopAR-Key', self::KEY)
            ->postJson(route('n8n.shoe-submissions.failure', 'leather-loafer'), ['message' => 'Tripo could not make the model.'])
            ->assertOk();

        $submission->refresh();
        $this->assertSame(ShoeSubmissionStatus::Failed, $submission->status);
        $this->assertSame('Tripo could not make the model.', $submission->message);
    }
}
