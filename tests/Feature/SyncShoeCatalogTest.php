<?php

namespace Tests\Feature;

use App\Models\Shoe;
use App\Models\ShoeSubmission;
use App\ShoeSubmissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SyncShoeCatalogTest extends TestCase
{
    use RefreshDatabase;

    private string $catalogPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalogPath = storage_path('framework/testing/shoe-catalog-'.uniqid());
        File::ensureDirectoryExists($this->catalogPath);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->catalogPath);

        parent::tearDown();
    }

    /**
     * Writes a catalog entry the way the n8n workflow's "Prepare repo files" step does.
     */
    private function writeEntry(string $id, bool $published, array $overrides = []): void
    {
        File::put($this->catalogPath."/{$id}.json", json_encode([
            'id' => $id,
            'name' => 'Leather Loafer',
            'glb' => "models/shoes/{$id}.glb",
            'thumb' => "models/shoes/{$id}.webp",
            'effect' => "effects/shoes/{$id}.deepar",
            'fit' => ['scale' => 100, 'rotation' => [0, -90, 0], 'position' => [0.7, -1.8, -4.8]],
            'published' => $published,
            'source' => ['tripo_task_id' => 'abc', 'claude_verdict' => 'pass'],
            ...$overrides,
        ]));
    }

    private function sync(): void
    {
        $this->artisan('shopar:sync-shoes', ['--path' => $this->catalogPath])->assertSuccessful();
    }

    public function test_a_draft_from_n8n_is_added_hidden_at_the_end_of_the_library(): void
    {
        Shoe::factory()->count(2)->create();
        $submission = ShoeSubmission::factory()->create(['slug' => 'leather-loafer']);
        $this->writeEntry('leather-loafer', published: false);

        $this->sync();

        $shoe = Shoe::query()->where('slug', 'leather-loafer')->sole();
        $this->assertFalse($shoe->is_visible);
        $this->assertSame(3, $shoe->position);
        $this->assertSame('effects/shoes/leather-loafer.deepar', $shoe->effect);
        $this->assertSame(100, $shoe->fit['scale']);
        $this->assertSame(ShoeSubmissionStatus::ReadyForReview, $submission->refresh()->status);
    }

    public function test_approving_the_phone_test_unhides_the_shoe(): void
    {
        $submission = ShoeSubmission::factory()->create(['slug' => 'leather-loafer']);
        $this->writeEntry('leather-loafer', published: false);
        $this->sync();

        $this->writeEntry('leather-loafer', published: true);
        $this->sync();

        $this->assertTrue(Shoe::query()->where('slug', 'leather-loafer')->sole()->is_visible);
        $this->assertSame(ShoeSubmissionStatus::Published, $submission->refresh()->status);
    }

    public function test_later_syncs_keep_the_admins_changes(): void
    {
        ShoeSubmission::factory()->create(['slug' => 'leather-loafer']);
        $this->writeEntry('leather-loafer', published: true);
        $this->sync();
        $shoe = Shoe::query()->where('slug', 'leather-loafer')->sole();
        $shoe->update(['name' => 'Renamed by admin', 'is_visible' => false]);

        $this->sync();

        $shoe->refresh();
        $this->assertSame('Renamed by admin', $shoe->name);
        $this->assertFalse($shoe->is_visible);
        $this->assertSame(1, Shoe::query()->count());
    }

    public function test_entries_with_unsafe_paths_are_skipped(): void
    {
        $this->writeEntry('bad-shoe', published: true, overrides: ['glb' => '../../.env']);

        $this->artisan('shopar:sync-shoes', ['--path' => $this->catalogPath])
            ->expectsOutputToContain('Skipped bad-shoe.json')
            ->assertSuccessful();

        $this->assertDatabaseEmpty('shoes');
    }

    public function test_a_missing_catalog_folder_is_not_an_error(): void
    {
        $this->artisan('shopar:sync-shoes', ['--path' => $this->catalogPath.'/missing'])->assertSuccessful();
    }

    public function test_a_hidden_shoe_can_be_previewed_by_its_id_on_the_try_on_page(): void
    {
        Shoe::factory()->create(['name' => 'Listed Loafer']);
        Shoe::factory()->hidden()->create(['slug' => 'secret-sandal', 'name' => 'Secret Sandal']);

        $this->withoutVite()->get(route('tryon'))->assertDontSee('Secret Sandal');
        $this->withoutVite()->get(route('tryon', ['preview' => 'secret-sandal']))
            ->assertOk()
            ->assertSeeInOrder(['Secret Sandal (preview)', 'Listed Loafer']);
    }
}
