<?php

namespace Tests\Feature\Admin;

use App\Models\Shoe;
use App\Models\ShoeSubmission;
use App\ShoeEffectBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ShoeEffectBuilderTest extends TestCase
{
    use RefreshDatabase;

    private const EFFECT_PATH = 'effects/generated/leather-loafer.deepar';

    /**
     * What scripts/build-deepar-effect.py --auto reports for a shoe.
     *
     * @var array{scale: float, rotation: list<int>, position: list<float>}
     */
    private const AUTO_FIT = ['scale' => 92.593, 'rotation' => [0, -90, 0], 'position' => [0.7, -1.82, -4.6]];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('shoe_assets');
    }

    private function submissionWithGlb(array $attributes = []): ShoeSubmission
    {
        $submission = ShoeSubmission::factory()->modelReady()->create(['name' => 'Leather Loafer', 'slug' => 'leather-loafer', ...$attributes]);
        Storage::disk('shoe_assets')->put($submission->glb_path, 'glTF-model-bytes');

        return $submission;
    }

    /**
     * Stands in for the Python script: writes the effect and prints its fit,
     * or fails the way the script does.
     */
    private function fakeScript(int $exitCode = 0, string $errorOutput = ''): void
    {
        Process::fake(function (PendingProcess $process) use ($exitCode, $errorOutput) {
            if ($exitCode === 0) {
                Storage::disk('shoe_assets')->put(self::EFFECT_PATH, 'deepar-effect-bytes');
            }
            $output = $exitCode === 0
                ? "effect.deepar: 7137 vertices\nFIT ".json_encode(['fit' => self::AUTO_FIT, 'orientation' => ['toe' => '+x', 'shoe' => 'right']])
                : '';

            return Process::result($output, $errorOutput, $exitCode);
        });
    }

    public function test_the_effect_is_built_from_the_glb_and_its_fit_starts_the_preview(): void
    {
        $this->fakeScript();
        $submission = $this->submissionWithGlb(['preview_fit' => ['fit' => ['scale' => 1, 'rotation' => [0, 90, 90], 'position' => [0, 0, 0]], 'leg_mask' => false]]);

        $this->assertNull(app(ShoeEffectBuilder::class)->build($submission));

        $submission->refresh();
        $this->assertSame(self::EFFECT_PATH, $submission->effect_path);
        $this->assertNull($submission->effect_error);
        $this->assertEquals(self::AUTO_FIT, $submission->preview_fit['fit']);
        $this->assertFalse($submission->preview_fit['leg_mask']);
        $this->assertSame('+x', $submission->preview_fit['orientation']['toe']);
        Process::assertRan(fn (PendingProcess $process) => in_array('--auto', $process->command, true)
            && in_array(public_path('effects/shoes/street-runner.deepar'), $process->command, true)
            && in_array(Storage::disk('shoe_assets')->path('models/generated/leather-loafer.glb'), $process->command, true));
    }

    public function test_a_model_the_script_cannot_handle_keeps_its_glb_and_says_why(): void
    {
        $this->fakeScript(2, 'ERROR the GLB has 3 materials; only one is supported');
        $submission = $this->submissionWithGlb();

        $failure = app(ShoeEffectBuilder::class)->build($submission);

        $this->assertSame('The effect could not be built: the GLB has 3 materials; only one is supported.', $failure);
        $submission->refresh();
        $this->assertNull($submission->effect_path);
        $this->assertSame($failure, $submission->effect_error);
        Storage::disk('shoe_assets')->assertMissing(self::EFFECT_PATH);
    }

    public function test_a_server_without_python_says_what_to_install(): void
    {
        $this->fakeScript(127, 'sh: python3: not found');

        $failure = app(ShoeEffectBuilder::class)->build($this->submissionWithGlb());

        $this->assertStringContainsString('python3: not found', $failure);
        $this->assertStringContainsString('Python 3 and Pillow', $failure);
    }

    public function test_a_shoe_without_its_glb_is_not_built(): void
    {
        Process::fake();
        $submission = ShoeSubmission::factory()->modelReady()->create();

        $this->assertNotNull(app(ShoeEffectBuilder::class)->build($submission));

        Process::assertNothingRan();
    }

    public function test_an_approved_shoe_worn_as_its_glb_switches_to_the_effect(): void
    {
        $this->fakeScript();
        $submission = $this->submissionWithGlb();
        $shoe = Shoe::factory()->create(['slug' => 'leather-loafer', 'effect' => null, 'glb' => $submission->glb_path]);

        app(ShoeEffectBuilder::class)->build($submission);

        $shoe->refresh();
        $this->assertSame(self::EFFECT_PATH, $shoe->effect);
        $this->assertTrue($shoe->usesEffect());
        $this->assertEquals(self::AUTO_FIT, $shoe->fit);
    }

    public function test_the_command_builds_the_effects_that_are_missing_and_skips_failed_ones(): void
    {
        $this->fakeScript();
        $missing = $this->submissionWithGlb();
        $failed = ShoeSubmission::factory()->modelReady()->create(['effect_error' => 'Too many vertices.']);

        $this->artisan('shopar:build-effect')->assertSuccessful();

        $this->assertSame(self::EFFECT_PATH, $missing->refresh()->effect_path);
        $this->assertNull($failed->refresh()->effect_path);
        Process::assertRanTimes(fn () => true, 1);
    }

    public function test_the_command_retries_one_shoe_by_its_id(): void
    {
        $this->fakeScript();
        $submission = $this->submissionWithGlb(['effect_error' => 'Python was missing.']);

        $this->artisan('shopar:build-effect', ['submission' => $submission->id])->assertSuccessful();

        $this->assertSame(self::EFFECT_PATH, $submission->refresh()->effect_path);
        $this->assertNull($submission->effect_error);
    }

    public function test_the_real_script_stands_a_sample_shoe_on_the_foot(): void
    {
        $python = config('services.deepar.effect_builder.python');
        if (! Process::run([$python, '-c', 'import PIL'])->successful()) {
            $this->markTestSkipped("{$python} with Pillow is not installed.");
        }
        $submission = ShoeSubmission::factory()->modelReady()->create(['name' => 'Leather Loafer', 'slug' => 'leather-loafer']);
        Storage::disk('shoe_assets')->put($submission->glb_path, file_get_contents(public_path('models/samples/ballet-flat.glb')));

        $this->assertNull(app(ShoeEffectBuilder::class)->build($submission));

        $submission->refresh();
        $this->assertEquals(self::AUTO_FIT, $submission->preview_fit['fit']);
        $this->assertSame(['down' => '-y', 'toe' => '+x', 'big_toe' => '-z', 'shoe' => 'right'], array_intersect_key(
            $submission->preview_fit['orientation'],
            array_flip(['down', 'toe', 'big_toe', 'shoe']),
        ));
        $this->assertGreaterThan(100_000, Storage::disk('shoe_assets')->size(self::EFFECT_PATH));
    }
}
