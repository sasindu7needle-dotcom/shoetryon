<?php

namespace Tests\Feature\Admin;

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

    private function modelReadySubmission(): ShoeSubmission
    {
        return ShoeSubmission::factory()->create([
            'name' => 'Leather Loafer',
            'slug' => 'leather-loafer',
            'status' => ShoeSubmissionStatus::ModelReady,
            'photo_path' => 'models/generated/leather-loafer.jpg',
            'glb_path' => 'models/generated/leather-loafer.glb',
        ]);
    }

    private function effect(string $name = 'leather-loafer.deepar'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, 'deepar-effect-bytes');
    }

    public function test_page_shows_the_3d_model_and_the_effect_form(): void
    {
        $submission = $this->modelReadySubmission();

        $this->actingAs($this->admin)->withoutVite()->get(route('admin.shoe-submissions.show', $submission))
            ->assertOk()
            ->assertSee('data-model-preview="'.asset('models/generated/leather-loafer.glb').'"', false)
            ->assertSee('Download GLB')
            ->assertSee('Add to library');
    }

    public function test_page_says_the_model_is_still_being_made(): void
    {
        $submission = ShoeSubmission::factory()->create();

        $this->actingAs($this->admin)->withoutVite()->get(route('admin.shoe-submissions.show', $submission))
            ->assertOk()
            ->assertSee('Tripo is making the 3D model')
            ->assertDontSee('Add to library');
    }

    public function test_the_submission_list_links_to_each_shoe(): void
    {
        $submission = $this->modelReadySubmission();

        $this->actingAs($this->admin)->withoutVite()->get(route('admin.shoe-submissions.create'))
            ->assertSee(route('admin.shoe-submissions.show', $submission))
            ->assertSee('3D model ready');
    }

    public function test_the_effect_adds_the_shoe_to_the_library_hidden(): void
    {
        $submission = $this->modelReadySubmission();

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
        $submission = $this->modelReadySubmission();

        $this->actingAs($this->admin)->post(route('admin.shoe-submissions.library.store', $submission), ['effect' => $this->effect('shoe.zip')])
            ->assertSessionHasErrors('effect');

        $this->assertDatabaseEmpty('shoes');
    }

    public function test_a_shoe_without_a_model_cannot_be_added(): void
    {
        $submission = ShoeSubmission::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.shoe-submissions.library.store', $submission), ['effect' => $this->effect()])
            ->assertConflict();

        $this->assertDatabaseEmpty('shoes');
    }

    public function test_users_who_are_not_admins_cannot_review_shoes(): void
    {
        $submission = $this->modelReadySubmission();
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.shoe-submissions.show', $submission))->assertForbidden();
        $this->actingAs($user)->post(route('admin.shoe-submissions.library.store', $submission), ['effect' => $this->effect()])->assertForbidden();
    }
}
