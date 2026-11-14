<?php

namespace App\Console\Commands;

use App\Models\ShoeSubmission;
use App\ShoeEffectBuilder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('shopar:build-effect {submission? : The shoe submission id (when left out: every model without an effect that has not failed to build)}')]
#[Description('Build the DeepAR effect for shoes made from photos, so DeepAR draws them on the tracked feet')]
class BuildShoeEffect extends Command
{
    /**
     * For shoes whose GLB arrived before effects were built automatically
     * (the container runs it on every start), or, given a submission id, to
     * try one again after a failed build.
     */
    public function handle(ShoeEffectBuilder $builder): int
    {
        $submissions = $this->argument('submission') !== null
            ? ShoeSubmission::query()->whereKey($this->argument('submission'))->get()
            : ShoeSubmission::query()->whereNotNull('glb_path')->whereNull('effect_path')->whereNull('effect_error')->get();

        if ($submissions->isEmpty()) {
            $this->info('No shoe needs an effect.');

            return self::SUCCESS;
        }

        $failed = 0;

        foreach ($submissions as $submission) {
            $failure = $builder->build($submission);

            if ($failure === null) {
                $this->info("{$submission->name}: built {$submission->effect_path}.");
            } else {
                $failed++;
                $this->error("{$submission->name}: {$failure}");
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
