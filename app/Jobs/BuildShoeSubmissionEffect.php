<?php

namespace App\Jobs;

use App\Models\ShoeSubmission;
use App\ShoeEffectBuilder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Builds the DeepAR effect for a shoe whose GLB Tripo has just made. It takes
 * a few seconds, so n8n's callback is answered first.
 */
class BuildShoeSubmissionEffect implements ShouldQueue
{
    use Queueable;

    public function __construct(public ShoeSubmission $submission) {}

    public function handle(ShoeEffectBuilder $builder): void
    {
        $builder->build($this->submission);
    }
}
