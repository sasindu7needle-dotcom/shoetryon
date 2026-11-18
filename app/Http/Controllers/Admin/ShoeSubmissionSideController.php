<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateShoeSubmissionSideRequest;
use App\Models\ShoeSubmission;
use App\ShoeEffectBuilder;
use App\ShoeFitRoundStatus;
use App\ShoeSubmissionStatus;
use Illuminate\Http\RedirectResponse;

class ShoeSubmissionSideController extends Controller
{
    /**
     * Fixes which foot the shoe is for when the feet preview shows the
     * shoes on the wrong feet: the DeepAR effect is built again from the GLB,
     * mirrored the other way, and its fit starts over from the automatic one.
     */
    public function update(UpdateShoeSubmissionSideRequest $request, ShoeSubmission $submission, ShoeEffectBuilder $builder): RedirectResponse
    {
        abort_unless($submission->status === ShoeSubmissionStatus::ModelReady, 409, 'This shoe is no longer waiting for its model review.');

        if ($submission->fitRounds()->where('status', ShoeFitRoundStatus::Processing)->exists()) {
            return back()->with('error', "Still fixing {$submission->name}'s fit. Wait for it, then change the side.");
        }

        $side = $request->side();
        $submission->update(['shoe_side' => $side]);

        $failure = $builder->build($submission);

        if ($failure !== null) {
            return back()->with('error', "{$submission->name} is now a {$side->value} shoe, but its AR effect could not be rebuilt. {$failure} The preview draws the 3D model instead.");
        }

        return back()->with('status', "{$submission->name} is now a {$side->value} shoe. Preview it on your feet again.");
    }
}
