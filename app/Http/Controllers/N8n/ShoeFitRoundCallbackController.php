<?php

namespace App\Http\Controllers\N8n;

use App\Http\Controllers\Controller;
use App\Http\Requests\N8n\StoreFitRoundResultRequest;
use App\Models\ShoeFitRound;
use App\ShoeFitRoundStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Where the n8n re-optimize workflow sends Claude's answer to the admin's
 * comment on a shoe's fit.
 */
class ShoeFitRoundCallbackController extends Controller
{
    /**
     * Saves Claude's new fit to the shoe (it takes effect on the next try-on,
     * no rebuild needed), or records that Claude found nothing to change.
     */
    public function storeResult(StoreFitRoundResultRequest $request, ShoeFitRound $round): JsonResponse
    {
        $this->ensureProcessing($round);

        if (! $request->boolean('changed')) {
            $round->update(['status' => ShoeFitRoundStatus::NoChange, 'message' => $request->validated('note')]);

            return response()->json(['status' => $round->status->value]);
        }

        $fit = $request->fit();

        DB::transaction(function () use ($round, $fit, $request) {
            $round->shoe->update(['fit' => $fit]);
            $round->update([
                'status' => ShoeFitRoundStatus::Applied,
                'new_fit' => $fit,
                'message' => $request->validated('note'),
            ]);
        });

        return response()->json(['status' => $round->status->value]);
    }

    /**
     * Records why the workflow stopped.
     */
    public function storeFailure(Request $request, ShoeFitRound $round): JsonResponse
    {
        $validated = $request->validate(['message' => ['required', 'string', 'max:2000']]);

        $this->ensureProcessing($round);
        $round->update(['status' => ShoeFitRoundStatus::Failed, 'message' => $validated['message']]);

        return response()->json(['status' => $round->status->value]);
    }

    /**
     * Each round is answered once, so a retried or replayed call cannot
     * overwrite a fit the admin has changed since.
     */
    private function ensureProcessing(ShoeFitRound $round): void
    {
        abort_unless($round->status === ShoeFitRoundStatus::Processing, Response::HTTP_CONFLICT, 'This round has already been answered.');
    }
}
