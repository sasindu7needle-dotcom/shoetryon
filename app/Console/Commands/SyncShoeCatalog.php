<?php

namespace App\Console\Commands;

use App\Models\Shoe;
use App\Models\ShoeSubmission;
use App\ShoeSubmissionStatus;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;

#[Signature('shopar:sync-shoes {--path= : Folder of catalog entries (defaults to resources/shoes)}')]
#[Description('Add the shoes the n8n workflow committed (resources/shoes/*.json) to the shoe library')]
class SyncShoeCatalog extends Command
{
    /**
     * Each finished shoe arrives as resources/shoes/<id>.json with
     * `published: false`; the workflow flips it to true when the admin
     * approves the phone test. A new entry is added to the end of the
     * library, hidden until it is published. Once a shoe is in the library
     * the admin's edits win: later syncs only unhide it the first time its
     * entry is published.
     */
    public function handle(): int
    {
        $folder = (string) ($this->option('path') ?: resource_path('shoes'));

        if (! File::isDirectory($folder)) {
            $this->info("No catalog entries to sync ({$folder} does not exist).");

            return self::SUCCESS;
        }

        $added = 0;
        $published = 0;

        foreach (File::glob($folder.DIRECTORY_SEPARATOR.'*.json') as $file) {
            $entry = json_decode((string) File::get($file), true);

            if (! is_array($entry) || Validator::make($entry, $this->entryRules())->fails()) {
                $this->warn('Skipped '.basename($file).': it is not a valid catalog entry.');

                continue;
            }

            $isPublished = (bool) ($entry['published'] ?? false);
            $submission = ShoeSubmission::query()
                ->where('slug', $entry['id'])
                ->whereIn('status', ShoeSubmissionStatus::claimingId())
                ->latest('id')
                ->first();
            $shoe = Shoe::query()->where('slug', $entry['id'])->first();

            if ($shoe === null) {
                Shoe::query()->create([
                    'slug' => $entry['id'],
                    'name' => $entry['name'],
                    'glb' => $entry['glb'],
                    'thumb' => $entry['thumb'],
                    'effect' => $entry['effect'],
                    'fit' => $entry['fit'] ?? null,
                    'is_visible' => $isPublished,
                ]);
                $submission?->update([
                    'status' => $isPublished ? ShoeSubmissionStatus::Published : ShoeSubmissionStatus::ReadyForReview,
                    'message' => $isPublished ? 'Published.' : 'In the shoe library, hidden until the phone test is approved.',
                ]);
                $added++;
                $this->line("Added {$entry['id']}".($isPublished ? '' : ' (hidden until published)'));

                continue;
            }

            if ($isPublished && $submission?->status === ShoeSubmissionStatus::ReadyForReview) {
                $shoe->update(['is_visible' => true]);
                $submission->update(['status' => ShoeSubmissionStatus::Published, 'message' => 'Published.']);
                $published++;
                $this->line("Published {$entry['id']}");
            }
        }

        $this->info("Shoe catalog synced: {$added} added, {$published} published.");

        return self::SUCCESS;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function entryRules(): array
    {
        $publicPath = ['required', 'string', 'max:255', 'regex:#^[A-Za-z0-9/._-]+$#', 'not_regex:#\.\.#'];

        return [
            'id' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'name' => ['required', 'string', 'max:80'],
            'glb' => $publicPath,
            'thumb' => $publicPath,
            'effect' => $publicPath,
            'fit' => ['nullable', 'array'],
            'published' => ['nullable', 'boolean'],
        ];
    }
}
