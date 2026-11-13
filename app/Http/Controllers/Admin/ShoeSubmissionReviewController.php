<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewShoeSubmissionRequest;
use App\Models\Shoe;
use App\Models\ShoeSubmission;
use App\ShoeFitRoundStatus;
use App\ShoeSubmissionStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ShoeSubmissionReviewController extends Controller
{
    /**
     * Records the admin's verdict on Tripo's 3D model after previewing it on
     * their feet. An approved model joins the library straight away, hidden,
     * worn as its GLB with the fit the admin set in the preview, so no DeepAR
     * effect has to be built. A rejected one frees the shoe's id so it can be
     * sent again with better photos.
     */
    public function store(ReviewShoeSubmissionRequest $request, ShoeSubmission $submission): RedirectResponse
    {
        abort_unless($submission->status === ShoeSubmissionStatus::ModelReady, 409, 'This shoe has no 3D model waiting for review.');

        $isApproval = $request->isApproval();

        if ($isApproval && $submission->fitRounds()->where('status', ShoeFitRoundStatus::Processing)->exists()) {
            return back()->withInput()->with('error', "Claude is still changing {$submission->name}'s preview fit. Wait for it, check it on your feet, then approve.");
        }

        if ($isApproval && Shoe::query()->where('slug', $submission->slug)->exists()) {
            return back()->withInput()->with('error', "The library already has a shoe with the id “{$submission->slug}”.");
        }

        DB::transaction(function () use ($request, $submission, $isApproval) {
            if ($isApproval) {
                Shoe::query()->create([
                    'slug' => $submission->slug,
                    'name' => $submission->name,
                    'glb' => $submission->glb_path,
                    'thumb' => $submission->photo_path,
                    'effect' => null,
                    'fit' => $submission->preview_fit['fit'] ?? Shoe::FEET_DEFAULT_FIT,
                    'leg_mask' => (bool) ($submission->preview_fit['leg_mask'] ?? true),
                    'is_visible' => false,
                ]);
            }

            $submission->update([
                'status' => $isApproval ? ShoeSubmissionStatus::ReadyForReview : ShoeSubmissionStatus::Rejected,
                'message' => $isApproval
                    ? 'In the shoe library, hidden until it passes the live try-on.'
                    : 'The 3D model was rejected. Send the shoe again from the Add new shoe page.',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_checks' => $request->passedChecks(),
                'review_note' => trim((string) $request->validated('note')) ?: null,
            ]);
        });

        return redirect()->route('admin.shoe-submissions.show', $submission)->with('status', $isApproval
            ? "{$submission->name} was approved and added to the library, hidden. Try it on live, then publish it or tell Claude what to fix."
            : "{$submission->name}'s 3D model was rejected. Send the shoe again with clearer photos.");
    }
}
