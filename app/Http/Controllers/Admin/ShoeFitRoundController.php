<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreShoeFitRoundRequest;
use App\Models\Shoe;
use App\Models\ShoeFitRound;
use App\ShoeFitRoundStatus;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShoeFitRoundController extends Controller
{
    /**
     * Sends the admin's comment (and phone screenshot) on how the shoe sits
     * to the n8n re-optimize webhook. Claude answers with a new fit, which
     * n8n posts back to /api/n8n/fit-rounds/<round>/result.
     */
    public function store(StoreShoeFitRoundRequest $request, Shoe $shoe): RedirectResponse
    {
        abort_unless(filled(config('services.n8n.reoptimize_url')) && filled(config('services.n8n.shopar_key')), 503, 'The n8n re-optimize webhook is not set.');

        if ($shoe->fitRounds()->where('status', ShoeFitRoundStatus::Processing)->exists()) {
            return back()->with('error', "Claude is still working on the last request for {$shoe->name}. Wait for it to finish.");
        }

        $round = $shoe->fitRounds()->create([
            'user_id' => $request->user()->id,
            'comment' => $request->validated('comment'),
            'status' => ShoeFitRoundStatus::Processing,
            'previous_fit' => $shoe->fit,
        ]);

        $failure = $this->sendToWorkflow($request, $shoe, $round);

        if ($failure !== null) {
            $round->update(['status' => ShoeFitRoundStatus::Failed, 'message' => $failure]);

            return back()->withInput()->with('error', "The request could not be sent to n8n. {$failure}");
        }

        return back()->with('status', "Sent to Claude. The new fit for {$shoe->name} is saved here in about a minute; reload the page, then test it on your phone.");
    }

    /**
     * Returns why the request failed, or null when n8n accepted it.
     */
    private function sendToWorkflow(StoreShoeFitRoundRequest $request, Shoe $shoe, ShoeFitRound $round): ?string
    {
        $screenshot = $request->file('screenshot');

        $payload = [
            'round_id' => $round->id,
            'comment' => $round->comment,
            'shoe' => [
                'id' => $shoe->slug,
                'name' => $shoe->name,
                'fit' => $shoe->fit,
            ],
            'reference_fits' => collect(config('shoes'))->mapWithKeys(fn (array $entry) => [$entry['id'] => $entry['fit']]),
            'screenshot' => $screenshot === null ? null : [
                'media_type' => $screenshot->getMimeType(),
                'data' => base64_encode($screenshot->get()),
            ],
            'result_url' => route('n8n.fit-rounds.result', $round),
            'failure_url' => route('n8n.fit-rounds.failure', $round),
        ];

        try {
            $response = Http::timeout((int) config('services.n8n.timeout'))
                ->withHeader('X-ShopAR-Key', (string) config('services.n8n.shopar_key'))
                ->acceptJson()
                ->post((string) config('services.n8n.reoptimize_url'), $payload);
        } catch (ConnectionException $exception) {
            Log::warning('Could not reach the n8n re-optimize webhook.', ['round' => $round->id, 'error' => $exception->getMessage()]);

            return 'n8n could not be reached. Check that it is running and that N8N_REOPTIMIZE_URL is right.';
        }

        if ($response->successful()) {
            return null;
        }

        Log::warning('The n8n re-optimize webhook refused a request.', ['round' => $round->id, 'status' => $response->status(), 'body' => mb_substr($response->body(), 0, 500)]);

        return match ($response->status()) {
            401, 403 => 'n8n refused the key. Check that N8N_SHOPAR_KEY matches the "ShopAR key" credential in n8n.',
            404 => 'n8n has no webhook at that address. Publish the workflow and use its production webhook URL.',
            default => "n8n answered with an error (HTTP {$response->status()}).",
        };
    }
}
