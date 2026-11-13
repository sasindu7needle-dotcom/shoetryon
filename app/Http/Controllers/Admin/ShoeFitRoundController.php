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
    /** The shoe is worn through a DeepAR effect built in DeepAR Studio. */
    public const RENDERER_EFFECT = 'deepar_effect';

    /** The shoe is worn as its GLB, drawn by three.js on the feet DeepAR tracks. */
    public const RENDERER_GLB = 'glb_on_feet';

    /**
     * What the fit values mean for each renderer, sent to Claude with the shoe.
     */
    public const EFFECT_FIT_GUIDE = 'The fit moves the shoe model inside its DeepAR effect. scale multiplies the GLB, which is in metres (about 90 makes a shoe foot-sized); rotation is Euler degrees [x, y, z]; position is in DeepAR foot units (a foot is about 24 long) along [width, height, length]; stretch is an optional [width, height, length] multiplier. Compare with the reference fits of shoes that already sit right.';

    public const GLB_FIT_GUIDE = 'The GLB is already centred on the tracked foot and sized so its longest side is one foot long, with that side along the foot\'s length. scale multiplies that size (1 = one foot long; 1.05 is 5% bigger); rotation is Euler degrees [x, y, z] applied to the shoe on the foot (y turns it around, 180 on z flips it upside down); position is in DeepAR foot units (a foot is about 24 long) as [sideways, up, towards the toes]; stretch is an optional [width, height, length] multiplier. Change values in small steps, e.g. 0.5 to 2 units of position.';

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
        $usesEffect = $shoe->usesEffect();

        $payload = [
            'round_id' => $round->id,
            'comment' => $round->comment,
            'renderer' => $usesEffect ? self::RENDERER_EFFECT : self::RENDERER_GLB,
            'fit_guide' => $usesEffect ? self::EFFECT_FIT_GUIDE : self::GLB_FIT_GUIDE,
            'shoe' => [
                'id' => $shoe->slug,
                'name' => $shoe->name,
                'fit' => $shoe->fit ?? ($usesEffect ? null : Shoe::FEET_DEFAULT_FIT),
            ],
            'reference_fits' => $usesEffect
                ? collect(config('shoes'))->mapWithKeys(fn (array $entry) => [$entry['id'] => $entry['fit']])
                : (object) [],
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
