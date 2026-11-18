<?php

namespace App\Http\Controllers\Admin;

use App\FitRoundWorkflow;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreShoeFitRoundRequest;
use App\Models\Shoe;
use App\ShoeFitRoundStatus;
use Illuminate\Http\RedirectResponse;

class ShoeFitRoundController extends Controller
{
    /**
     * Sends the admin's comment (and phone screenshot) on how the shoe sits
     * to the n8n re-optimize webhook. Claude answers with a new fit, which
     * n8n posts back to /api/n8n/fit-rounds/<round>/result.
     */
    public function store(StoreShoeFitRoundRequest $request, Shoe $shoe, FitRoundWorkflow $workflow): RedirectResponse
    {
        abort_unless(FitRoundWorkflow::isConfigured(), 503, 'Fit fixes are not set up yet.');

        if ($shoe->fitRounds()->where('status', ShoeFitRoundStatus::Processing)->exists()) {
            return back()->with('error', "Still working on the last fix for {$shoe->name}. Wait for it to finish.");
        }

        $round = $shoe->fitRounds()->create([
            'user_id' => $request->user()->id,
            'comment' => $request->validated('comment'),
            'status' => ShoeFitRoundStatus::Processing,
            'previous_fit' => $shoe->fit,
        ]);

        $usesEffect = $shoe->usesEffect();
        $failure = $workflow->send($round, [
            'id' => $shoe->slug,
            'name' => $shoe->name,
            'fit' => $shoe->fit ?? ($usesEffect ? null : Shoe::FEET_DEFAULT_FIT),
        ], $usesEffect, $request->file('screenshot'));

        if ($failure !== null) {
            $round->update(['status' => ShoeFitRoundStatus::Failed, 'message' => $failure]);

            return back()->withInput()->with('error', "The fix could not be sent. {$failure}");
        }

        return back()->with('status', "Sent. The new fit for {$shoe->name} is ready in about a minute.");
    }
}
