<?php

namespace App\Http\Controllers\Admin;

use App\FitRoundWorkflow;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSubmissionFitRoundRequest;
use App\Models\Shoe;
use App\Models\ShoeFitRound;
use App\Models\ShoeSubmission;
use App\ShoeFitRoundStatus;
use App\ShoeSubmissionStatus;
use Illuminate\Http\JsonResponse;

class ShoeSubmissionFitRoundController extends Controller
{
    /**
     * The latest Claude round on the preview fit, so the open feet preview
     * can pick up Claude's answer without a reload.
     */
    public function show(ShoeSubmission $submission): JsonResponse
    {
        return response()->json(['round' => $this->describe($submission->fitRounds()->latest('id')->first())]);
    }

    /**
     * Sends a photo taken in the feet preview (camera and shoes together) to
     * the n8n re-optimize webhook. Claude's new fit comes back to the
     * submission's preview fit, and becomes the shoe's fit when it is approved.
     */
    public function store(StoreSubmissionFitRoundRequest $request, ShoeSubmission $submission, FitRoundWorkflow $workflow): JsonResponse
    {
        abort_unless(FitRoundWorkflow::isConfigured(), 503, 'The n8n re-optimize webhook is not set.');
        abort_unless($submission->status === ShoeSubmissionStatus::ModelReady, 409, 'This shoe is no longer waiting for its model review.');

        if ($submission->fitRounds()->where('status', ShoeFitRoundStatus::Processing)->exists()) {
            return response()->json(['message' => 'Claude is still working on the last photo. Wait for it to finish.'], 409);
        }

        $fit = $submission->preview_fit['fit'] ?? Shoe::FEET_DEFAULT_FIT;
        $round = $submission->fitRounds()->create([
            'user_id' => $request->user()->id,
            'comment' => $request->comment(),
            'status' => ShoeFitRoundStatus::Processing,
            'previous_fit' => $fit,
        ]);

        $failure = $workflow->send($round, [
            'id' => $submission->slug,
            'name' => $submission->name,
            'fit' => $fit,
        ], $submission->effect_path !== null, $request->file('photo'));

        if ($failure !== null) {
            $round->update(['status' => ShoeFitRoundStatus::Failed, 'message' => $failure]);

            return response()->json(['message' => "The photo could not be sent. {$failure}", 'round' => $this->describe($round)], 502);
        }

        return response()->json(['round' => $this->describe($round)], 201);
    }

    /**
     * @return array{id: int, status: string, label: string, message: string|null, fit: array<string, mixed>|null}|null
     */
    private function describe(?ShoeFitRound $round): ?array
    {
        if ($round === null) {
            return null;
        }

        return [
            'id' => $round->id,
            'status' => $round->status->value,
            'label' => $round->status->label(),
            'message' => $round->message,
            'fit' => $round->new_fit,
        ];
    }
}
