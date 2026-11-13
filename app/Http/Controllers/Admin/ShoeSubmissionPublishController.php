<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shoe;
use App\Models\ShoeSubmission;
use App\ShoeFitRoundStatus;
use App\ShoeSubmissionStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ShoeSubmissionPublishController extends Controller
{
    /**
     * Records that the shoe passed the admin's live try-on and shows it on
     * the site. Shoes that still sit wrong go back through Claude instead,
     * via the re-optimize form on the same page.
     */
    public function store(ShoeSubmission $submission): RedirectResponse
    {
        abort_unless($submission->status === ShoeSubmissionStatus::ReadyForReview, 409, 'This shoe is not waiting for its try-on review.');

        $shoe = Shoe::query()->where('slug', $submission->slug)->first();

        abort_if($shoe === null, 409, 'This shoe is not in the library.');

        if ($shoe->fitRounds()->where('status', ShoeFitRoundStatus::Processing)->exists()) {
            return back()->with('error', "Claude is still changing {$shoe->name}'s fit. Wait for it, try the shoe on again, then publish it.");
        }

        DB::transaction(function () use ($shoe, $submission) {
            $shoe->update(['is_visible' => true]);
            $submission->update([
                'status' => ShoeSubmissionStatus::Published,
                'message' => 'Passed the live try-on and published.',
            ]);
        });

        return redirect()->route('admin.shoe-submissions.show', $submission)->with('status', "{$shoe->name} passed the try-on and is now visible on the site.");
    }
}
