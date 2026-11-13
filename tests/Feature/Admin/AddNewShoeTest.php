<?php

namespace Tests\Feature\Admin;

use App\Models\Shoe;
use App\Models\ShoeSubmission;
use App\Models\User;
use App\ShoeSubmissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AddNewShoeTest extends TestCase
{
    use RefreshDatabase;

    private const FORM_URL = 'https://n8n.example.test/form/shopar-new-shoe';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('shoe_assets');
        $this->admin = User::factory()->admin()->create();
        config([
            'services.n8n.new_shoe_form_url' => self::FORM_URL,
            'services.n8n.form_username' => 'form-user',
            'services.n8n.form_password' => 'form-secret',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validSubmission(array $overrides = []): array
    {
        return [
            'name' => 'Leather Loafer',
            'shoe_type' => 'low',
            'length_cm' => '27.5',
            'front_photo' => UploadedFile::fake()->image('front.jpg', 800, 600),
            'right_photo' => UploadedFile::fake()->image('right.jpg', 800, 600),
            'back_photo' => UploadedFile::fake()->image('back.png', 800, 600),
            'left_photo' => UploadedFile::fake()->image('left.jpg', 800, 600),
            ...$overrides,
        ];
    }

    public function test_page_renders_and_sidebar_links_to_it(): void
    {
        $response = $this->actingAs($this->admin)->withoutVite()->get(route('admin.shoe-submissions.create'));

        $response->assertOk();
        $response->assertSee('Add new shoe');
        $this->assertMatchesRegularExpression(
            '#href="'.preg_quote(route('admin.shoe-submissions.create'), '#').'"\s+aria-current="page"#',
            $response->getContent(),
        );
    }

    public function test_page_warns_when_n8n_is_not_configured(): void
    {
        config(['services.n8n.new_shoe_form_url' => null]);

        $this->actingAs($this->admin)->withoutVite()->get(route('admin.shoe-submissions.create'))
            ->assertSee('The n8n workflow is not connected yet.');
    }

    public function test_photos_are_sent_to_the_n8n_form_in_its_field_order(): void
    {
        Http::fake([self::FORM_URL => Http::response(['formSubmittedText' => 'Thanks'])]);

        $response = $this->actingAs($this->admin)->post(route('admin.shoe-submissions.store'), $this->validSubmission());

        $response->assertRedirect(route('admin.shoe-submissions.create'));
        $response->assertSessionHas('status');

        Http::assertSent(function (Request $request) {
            $parts = collect($request->data())->keyBy('name');

            return $request->url() === self::FORM_URL
                && $request->hasHeader('Authorization', 'Basic '.base64_encode('form-user:form-secret'))
                && $request->isMultipart()
                && $parts['field-0']['contents'] === 'Leather Loafer'
                && $parts['field-1']['contents'] === 'Low shoe (sneaker, flat, loafer, sandal)'
                && $parts['field-2']['contents'] === '27.5'
                && $parts['field-3']['filename'] === 'leather-loafer-front_photo.jpg'
                && $parts['field-4']['filename'] === 'leather-loafer-right_photo.jpg'
                && $parts['field-5']['filename'] === 'leather-loafer-back_photo.png'
                && $parts['field-6']['filename'] === 'leather-loafer-left_photo.jpg';
        });

        $submission = ShoeSubmission::query()->sole();
        $this->assertSame('leather-loafer', $submission->slug);
        $this->assertSame(ShoeSubmissionStatus::Processing, $submission->status);
        $this->assertTrue($submission->user->is($this->admin));
    }

    public function test_the_front_photo_is_kept_as_the_shoes_thumbnail(): void
    {
        Http::fake([self::FORM_URL => Http::response()]);

        $this->actingAs($this->admin)->post(route('admin.shoe-submissions.store'), $this->validSubmission());

        $this->assertSame('models/generated/leather-loafer.jpg', ShoeSubmission::query()->sole()->photo_path);
        Storage::disk('shoe_assets')->assertExists('models/generated/leather-loafer.jpg');
    }

    public function test_boots_are_sent_with_the_boot_option(): void
    {
        Http::fake([self::FORM_URL => Http::response()]);

        $this->actingAs($this->admin)->post(route('admin.shoe-submissions.store'), $this->validSubmission(['shoe_type' => 'boot']));

        Http::assertSent(fn (Request $request) => collect($request->data())->firstWhere('name', 'field-1')['contents'] === 'Boot (covers the ankle)');
    }

    public function test_a_refusal_from_n8n_is_recorded_and_shown(): void
    {
        Http::fake([self::FORM_URL => Http::response('', 401)]);

        $response = $this->actingAs($this->admin)->from(route('admin.shoe-submissions.create'))
            ->post(route('admin.shoe-submissions.store'), $this->validSubmission());

        $response->assertRedirect(route('admin.shoe-submissions.create'));
        $response->assertSessionHas('error', fn (string $error) => str_contains($error, 'N8N_FORM_USERNAME'));
        $response->assertSessionHasInput('name', 'Leather Loafer');
        $this->assertSame(ShoeSubmissionStatus::Failed, ShoeSubmission::query()->sole()->status);
    }

    public function test_an_unreachable_n8n_is_recorded_as_failed(): void
    {
        Http::fake([self::FORM_URL => Http::failedConnection()]);

        $this->actingAs($this->admin)->post(route('admin.shoe-submissions.store'), $this->validSubmission())
            ->assertSessionHas('error', fn (string $error) => str_contains($error, 'could not be reached'));

        $this->assertSame(ShoeSubmissionStatus::Failed, ShoeSubmission::query()->sole()->status);
    }

    public function test_nothing_is_sent_while_n8n_is_not_configured(): void
    {
        Http::fake();
        config(['services.n8n.new_shoe_form_url' => null]);

        $this->actingAs($this->admin)->post(route('admin.shoe-submissions.store'), $this->validSubmission())->assertServiceUnavailable();

        Http::assertNothingSent();
        $this->assertDatabaseEmpty('shoe_submissions');
    }

    public function test_valid_details_and_a_front_photo_are_required(): void
    {
        Http::fake();

        $response = $this->actingAs($this->admin)->post(route('admin.shoe-submissions.store'), [
            'name' => '',
            'shoe_type' => 'sandal',
            'length_cm' => '50',
        ]);

        $response->assertSessionHasErrors(['name', 'shoe_type', 'length_cm', 'front_photo']);
        $response->assertSessionDoesntHaveErrors(['right_photo', 'back_photo', 'left_photo']);
        Http::assertNothingSent();
    }

    public function test_optional_photos_must_still_be_images(): void
    {
        Http::fake();

        $this->actingAs($this->admin)->post(route('admin.shoe-submissions.store'), $this->validSubmission([
            'back_photo' => UploadedFile::fake()->create('back.pdf', 100, 'application/pdf'),
        ]))->assertSessionHasErrors('back_photo');

        Http::assertNothingSent();
    }

    public function test_a_shoe_can_be_sent_with_only_the_front_photo(): void
    {
        Http::fake([self::FORM_URL => Http::response()]);

        $response = $this->actingAs($this->admin)->post(route('admin.shoe-submissions.store'), $this->validSubmission([
            'right_photo' => null,
            'back_photo' => null,
            'left_photo' => null,
        ]));

        $response->assertSessionHasNoErrors();
        Http::assertSent(function (Request $request) {
            $parts = collect($request->data())->keyBy('name');

            return $parts->keys()->all() === ['field-0', 'field-1', 'field-2', 'field-3']
                && $parts['field-3']['filename'] === 'leather-loafer-front_photo.jpg';
        });
        $this->assertSame(ShoeSubmissionStatus::Processing, ShoeSubmission::query()->sole()->status);
    }

    public function test_optional_photos_keep_their_form_position_when_others_are_left_out(): void
    {
        Http::fake([self::FORM_URL => Http::response()]);

        $this->actingAs($this->admin)->post(route('admin.shoe-submissions.store'), $this->validSubmission([
            'right_photo' => null,
            'back_photo' => null,
        ]));

        Http::assertSent(function (Request $request) {
            $parts = collect($request->data())->keyBy('name');

            return ! $parts->has('field-4') && ! $parts->has('field-5')
                && $parts['field-6']['filename'] === 'leather-loafer-left_photo.jpg';
        });
    }

    public function test_photos_over_twenty_megabytes_are_refused(): void
    {
        Http::fake();

        $this->actingAs($this->admin)->post(route('admin.shoe-submissions.store'), $this->validSubmission([
            'front_photo' => UploadedFile::fake()->image('front.jpg')->size(20481),
        ]))->assertSessionHasErrors(['front_photo' => 'The front photo must be 20 MB or smaller.']);
    }

    public function test_a_name_whose_id_is_already_in_the_library_is_refused(): void
    {
        Http::fake();
        Shoe::factory()->create(['slug' => 'leather-loafer']);

        $this->actingAs($this->admin)->post(route('admin.shoe-submissions.store'), $this->validSubmission(['name' => 'Leather  Loafer!']))
            ->assertSessionHasErrors('name');

        Http::assertNothingSent();
    }

    public function test_a_name_already_being_made_is_refused_but_a_failed_one_can_be_retried(): void
    {
        Http::fake([self::FORM_URL => Http::response()]);
        ShoeSubmission::factory()->create(['name' => 'Trail Boot', 'slug' => 'trail-boot']);
        ShoeSubmission::factory()->failed()->create(['name' => 'Leather Loafer', 'slug' => 'leather-loafer']);

        $this->actingAs($this->admin)->post(route('admin.shoe-submissions.store'), $this->validSubmission(['name' => 'Trail Boot']))
            ->assertSessionHasErrors('name');

        $this->actingAs($this->admin)->post(route('admin.shoe-submissions.store'), $this->validSubmission())
            ->assertSessionHasNoErrors();
    }

    public function test_slugs_match_the_n8n_workflow(): void
    {
        $this->assertSame('cafe-creme-loafer', ShoeSubmission::slugFor('  Café Crème Loafer! '));
        $this->assertSame('air-max-90', ShoeSubmission::slugFor('Air--Max 90'));
        $this->assertSame(str_repeat('a', 40), ShoeSubmission::slugFor(str_repeat('a', 50)));
        $this->assertSame('', ShoeSubmission::slugFor('!!!'));
    }

    public function test_recent_submissions_are_listed_with_their_status(): void
    {
        ShoeSubmission::factory()->readyForReview()->create(['name' => 'Desert Boot']);
        ShoeSubmission::factory()->failed()->create(['name' => 'Canvas Slip-On']);

        $this->actingAs($this->admin)->withoutVite()->get(route('admin.shoe-submissions.create'))
            ->assertSeeInOrder(['Canvas Slip-On', 'Failed', 'Desert Boot', 'Ready for phone test']);
    }

    public function test_users_who_are_not_admins_cannot_add_shoes(): void
    {
        Http::fake();

        $this->actingAs(User::factory()->create())->post(route('admin.shoe-submissions.store'), $this->validSubmission())->assertForbidden();

        Http::assertNothingSent();
    }
}
