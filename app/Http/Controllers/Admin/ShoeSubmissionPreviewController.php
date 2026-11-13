<?php

namespace App\Http\Controllers\Admin;

use App\FitRoundWorkflow;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveFeetFitRequest;
use App\Http\Requests\Admin\StoreShoeFitRoundRequest;
use App\Models\Shoe;
use App\Models\ShoeSubmission;
use App\ShoeFitRoundStatus;
use App\ShoeSubmissionStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class ShoeSubmissionPreviewController extends Controller
{
    /**
     * The phone camera view that puts Tripo's GLB on the admin's tracked
     * feet, so the 3D model can be judged on a real foot before it is
     * approved. It needs no DeepAR effect of its own: DeepAR only tracks the
     * feet and three.js draws the GLB on them.
     */
    public function show(ShoeSubmission $submission): View
    {
        abort_unless(
            $submission->glb_path !== null && Storage::disk('shoe_assets')->exists($submission->glb_path),
            404,
            'This shoe has no 3D model to preview.',
        );

        $isAwaitingReview = $submission->status === ShoeSubmissionStatus::ModelReady;
        $fit = $submission->preview_fit['fit'] ?? Shoe::FEET_DEFAULT_FIT;
        $latestRound = $submission->fitRounds()->latest('id')->first();
        $canAskClaude = $isAwaitingReview && FitRoundWorkflow::isConfigured();

        return view('admin.shoe-submissions.preview', [
            'submission' => $submission,
            'reviewUrl' => route('admin.shoe-submissions.show', $submission),
            'isAwaitingReview' => $isAwaitingReview,
            'preview' => [
                'licenseKey' => config('services.deepar.license_key'),
                'rootPath' => asset('vendor/deepar').'/',
                'modelUrl' => route('admin.shoe-submissions.files.show', [$submission, 'model']),
                'trackerEffect' => Shoe::trackerEffectUrl(),
                'fit' => $fit,
                // A fit Claude answered with is kept; any other saved fit is
                // replaced by the automatic one.
                'isClaudeFit' => $latestRound?->status === ShoeFitRoundStatus::Applied && $latestRound->new_fit == $fit,
                'legMask' => (bool) ($submission->preview_fit['leg_mask'] ?? true),
                'saveUrl' => $isAwaitingReview ? route('admin.shoe-submissions.preview.update', $submission) : null,
                'photoName' => $submission->slug.'-on-feet',
                'claudeUrl' => $canAskClaude ? route('admin.shoe-submissions.fit-rounds.store', $submission) : null,
                'claudeStatusUrl' => $canAskClaude ? route('admin.shoe-submissions.fit-rounds.latest', $submission) : null,
                'isClaudeWorking' => $latestRound?->status === ShoeFitRoundStatus::Processing,
                'maxPhotoBytes' => StoreShoeFitRoundRequest::MAX_SCREENSHOT_KILOBYTES * 1024,
            ],
        ]);
    }

    /**
     * Saves how the admin lined the GLB up on their feet. It becomes the
     * shoe's starting fit when the model is approved.
     */
    public function update(SaveFeetFitRequest $request, ShoeSubmission $submission): JsonResponse
    {
        abort_unless($submission->status === ShoeSubmissionStatus::ModelReady, 409, 'This shoe is no longer waiting for its model review.');

        $submission->update(['preview_fit' => $request->previewFit()]);

        return response()->json(['saved' => true]);
    }
}
