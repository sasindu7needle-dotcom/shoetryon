<?php

namespace Tests\Feature\N8n;

use App\Models\Shoe;
use App\Models\ShoeFitRound;
use App\ShoeFitRoundStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShoeFitRoundCallbackTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'shared-test-key';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.n8n.shopar_key' => self::KEY]);
    }

    public function test_claudes_new_fit_is_saved_to_the_shoe(): void
    {
        $shoe = Shoe::factory()->create(['fit' => ['scale' => 90, 'rotation' => [0, -90, 0], 'position' => [0.75, -1.8, -4.85]]]);
        $round = ShoeFitRound::factory()->for($shoe)->create();

        $response = $this->withHeader('X-ShopAR-Key', self::KEY)->postJson(route('n8n.fit-rounds.result', $round), [
            'changed' => true,
            'note' => 'Moved the shoe 0.5 forward so the heel sits inside it.',
            'fit' => ['scale' => 90, 'rotation' => [0, -90, 0], 'position' => [0.75, -1.8, -4.35], 'stretch' => [1, 1, 1]],
        ]);

        $response->assertOk()->assertJson(['status' => 'applied']);
        $expectedFit = ['scale' => 90, 'rotation' => [0, -90, 0], 'position' => [0.75, -1.8, -4.35]];
        $this->assertEquals($expectedFit, $shoe->refresh()->fit);
        $round->refresh();
        $this->assertSame(ShoeFitRoundStatus::Applied, $round->status);
        $this->assertEquals($expectedFit, $round->new_fit);
        $this->assertSame('Moved the shoe 0.5 forward so the heel sits inside it.', $round->message);
    }

    public function test_no_change_leaves_the_fit_alone(): void
    {
        $shoe = Shoe::factory()->create(['fit' => null]);
        $round = ShoeFitRound::factory()->for($shoe)->create();

        $this->withHeader('X-ShopAR-Key', self::KEY)
            ->postJson(route('n8n.fit-rounds.result', $round), ['changed' => false, 'note' => 'The model itself is wrong; it needs new photos.'])
            ->assertOk();

        $this->assertNull($shoe->refresh()->fit);
        $this->assertSame(ShoeFitRoundStatus::NoChange, $round->refresh()->status);
    }

    public function test_a_changed_fit_must_be_complete_and_in_range(): void
    {
        $round = ShoeFitRound::factory()->create();

        $this->withHeader('X-ShopAR-Key', self::KEY)
            ->postJson(route('n8n.fit-rounds.result', $round), [
                'changed' => true,
                'note' => 'x',
                'fit' => ['scale' => 0, 'rotation' => [0, 720, 0], 'position' => [0, 0]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fit.scale', 'fit.rotation.1', 'fit.position']);

        $this->assertSame(ShoeFitRoundStatus::Processing, $round->refresh()->status);
    }

    public function test_a_round_is_answered_only_once(): void
    {
        $shoe = Shoe::factory()->create();
        $round = ShoeFitRound::factory()->for($shoe)->applied()->create();
        $fitBefore = $shoe->fit;

        $this->withHeader('X-ShopAR-Key', self::KEY)
            ->postJson(route('n8n.fit-rounds.result', $round), [
                'changed' => true,
                'note' => 'replayed',
                'fit' => ['scale' => 50, 'rotation' => [0, 0, 0], 'position' => [0, 0, 0]],
            ])
            ->assertConflict();

        $this->assertEquals($fitBefore, $shoe->refresh()->fit);
    }

    public function test_n8n_reports_why_the_round_failed(): void
    {
        $round = ShoeFitRound::factory()->create();

        $this->withHeader('X-ShopAR-Key', self::KEY)
            ->postJson(route('n8n.fit-rounds.failure', $round), ['message' => 'Claude: plan the fit: timeout'])
            ->assertOk();

        $round->refresh();
        $this->assertSame(ShoeFitRoundStatus::Failed, $round->status);
        $this->assertSame('Claude: plan the fit: timeout', $round->message);
    }

    public function test_calls_without_the_right_key_are_refused(): void
    {
        $round = ShoeFitRound::factory()->create();

        $this->postJson(route('n8n.fit-rounds.result', $round), ['changed' => false, 'note' => 'x'])->assertUnauthorized();

        $this->assertSame(ShoeFitRoundStatus::Processing, $round->refresh()->status);
    }
}
