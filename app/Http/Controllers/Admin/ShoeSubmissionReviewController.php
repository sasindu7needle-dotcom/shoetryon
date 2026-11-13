<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewShoeSubmissionRequest;
use App\Models\ShoeSubmission;
use App\ShoeSubmissionStatus;
use Illuminate\Http\RedirectResponse;

class ShoeSubmissionReviewController extends Controller
{
    /**
     * Records the admin's verdict on Tripo's 3D model. An approved model
     * moves on to its DeepAR effect; a rejected one frees the shoe's id so
     * it can be sent again with better photos.
     */
    public function store(ReviewShoeSubmissionRequest $request, ShoeSubmission $submission): RedirectResponse
    {
        abort_unless($submission->status === ShoeSubmissionStatus::ModelReady, 409, 'This shoe has no 3D model waiting for review.');

        $isApproval = $request->isApproval();
        $note = trim((string) $request->validated('note')) ?: null;

        $submission->update([
            'status' => $isApproval ? ShoeSubmissionStatus::ModelApproved : ShoeSubmissionStatus::Rejected,
            'message' => $isApproval
                ? 'The 3D model was approved. Add its DeepAR effect to put it in the library.'
                : 'The 3D model was rejected. Send the shoe again from the Add new shoe page.',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_checks' => $request->passedChecks(),
            'review_note' => $note,
        ]);

        return redirect()->route('admin.shoe-submissions.show', $submission)->with('status', $isApproval
            ? "{$submission->name}'s 3D model was approved. Next, add its DeepAR effect."
            : "{$submission->name}'s 3D model was rejected. Send the shoe again with clearer photos.");
    }
}
