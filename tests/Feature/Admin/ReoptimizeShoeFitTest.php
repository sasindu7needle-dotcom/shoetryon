<?php

namespace Tests\Feature\Admin;

use App\Models\Shoe;
use App\Models\ShoeFitRound;
use App\Models\User;
use App\ShoeFitRoundStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReoptimizeShoeFitTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_URL = 'https://n8n.example.test/webhook/shopar-fit-reoptimize';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        config([
            'services.n8n.reoptimize_url' => self::WEBHOOK_URL,
            'services.n8n.shopar_key' => 'shared-test-key',
        ]);
    }

    public function test_the_comment_and_screenshot_are_sent_to_n8n(): void
    {
        Http::fake([self::WEBHOOK_URL => Http::response('Accepted')]);
        $shoe = Shoe::factory()->create(['slug' => 'street-runner']);

        $response = $this->actingAs($this->admin)->from(route('admin.shoes.edit', $shoe))->post(route('admin.shoes.fit-rounds.store', $shoe), [
            'comment' => 'The heel sticks out behind my foot.',
            'screenshot' => UploadedFile::fake()->createWithContent('phone.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==')),
        ]);

        $response->assertRedirect(route('admin.shoes.edit', $shoe));
        $response->assertSessionHas('status');
        $round = ShoeFitRound::query()->sole();
        $this->assertSame(ShoeFitRoundStatus::Processing, $round->status);
        $this->assertEquals($shoe->fit, $round->previous_fit);
        $this->assertTrue($round->user->is($this->admin));

        Http::assertSent(fn (Request $request) => $request->url() === self::WEBHOOK_URL
            && $request->hasHeader('X-ShopAR-Key', 'shared-test-key')
            && $request['round_id'] === $round->id
            && $request['comment'] === 'The heel sticks out behind my foot.'
            && $request['shoe']['id'] === 'street-runner'
            && $request['shoe']['fit'] == $shoe->fit
            && $request['screenshot']['media_type'] === 'image/png'
            && $request['screenshot']['data'] !== ''
            && isset($request['reference_fits']['street-runner'])
            && $request['result_url'] === route('n8n.fit-rounds.result', $round)
            && $request['failure_url'] === route('n8n.fit-rounds.failure', $round));
    }

    public function test_the_screenshot_is_optional(): void
    {
        Http::fake([self::WEBHOOK_URL => Http::response('Accepted')]);
        $shoe = Shoe::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.shoes.fit-rounds.store', $shoe), ['comment' => 'The shoe floats above the floor.'])
            ->assertSessionHasNoErrors();

        Http::assertSent(fn (Request $request) => $request['screenshot'] === null);
    }

    public function test_a_refusal_from_n8n_is_recorded_and_shown(): void
    {
        Http::fake([self::WEBHOOK_URL => Http::response('', 403)]);
        $shoe = Shoe::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.shoes.fit-rounds.store', $shoe), ['comment' => 'The shoe is too big.'])
            ->assertSessionHas('error', fn (string $error) => str_contains($error, 'N8N_SHOPAR_KEY'));

        $this->assertSame(ShoeFitRoundStatus::Failed, ShoeFitRound::query()->sole()->status);
    }

    public function test_a_second_request_waits_for_the_first(): void
    {
        Http::fake();
        $shoe = Shoe::factory()->create();
        ShoeFitRound::factory()->for($shoe)->create();

        $this->actingAs($this->admin)->post(route('admin.shoes.fit-rounds.store', $shoe), ['comment' => 'The shoe is too big.'])
            ->assertSessionHas('error');

        Http::assertNothingSent();
        $this->assertSame(1, ShoeFitRound::query()->count());
    }

    public function test_nothing_is_sent_while_the_webhook_is_not_configured(): void
    {
        Http::fake();
        config(['services.n8n.reoptimize_url' => null]);
        $shoe = Shoe::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.shoes.fit-rounds.store', $shoe), ['comment' => 'The shoe is too big.'])
            ->assertServiceUnavailable();

        Http::assertNothingSent();
    }

    public function test_a_comment_is_required(): void
    {
        Http::fake();
        $shoe = Shoe::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.shoes.fit-rounds.store', $shoe), ['comment' => ''])
            ->assertSessionHasErrors('comment');

        Http::assertNothingSent();
    }

    public function test_edit_page_lists_recent_rounds(): void
    {
        $shoe = Shoe::factory()->create();
        ShoeFitRound::factory()->for($shoe)->applied()->create(['comment' => 'Heel sticks out.']);

        $this->actingAs($this->admin)->withoutVite()->get(route('admin.shoes.edit', $shoe))
            ->assertOk()
            ->assertSee('Re-optimize with Claude')
            ->assertSee('Heel sticks out.')
            ->assertSee('Moved the shoe 0.5 forward.')
            ->assertSee('New fit saved');
    }

    public function test_users_who_are_not_admins_cannot_reoptimize(): void
    {
        Http::fake();
        $shoe = Shoe::factory()->create();

        $this->actingAs(User::factory()->create())->post(route('admin.shoes.fit-rounds.store', $shoe), ['comment' => 'Too big.'])->assertForbidden();

        Http::assertNothingSent();
    }
}
