<?php

namespace App;

use App\Models\ShoeFitRound;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends a "re-optimize with Claude" round to the n8n webhook. Claude answers
 * with a new fit, which n8n posts back to /api/n8n/fit-rounds/<round>/result.
 */
class FitRoundWorkflow
{
    /** The shoe is worn through a DeepAR effect built in DeepAR Studio. */
    public const RENDERER_EFFECT = 'deepar_effect';

    /** The shoe is worn as its GLB, drawn by three.js on the feet DeepAR tracks. */
    public const RENDERER_GLB = 'glb_on_feet';

    /**
     * What the fit values mean for each renderer, sent to Claude with the shoe.
     */
    public const EFFECT_FIT_GUIDE = 'The fit moves the shoe model inside its DeepAR effect. scale multiplies the GLB, which is in metres (about 90 makes a shoe foot-sized); rotation is Euler degrees [x, y, z]; position is in DeepAR foot units (a foot is about 24 long) along [width, height, length]; stretch is an optional [width, height, length] multiplier. Compare with the reference fits of shoes that already sit right.';

    public const GLB_FIT_GUIDE = 'The GLB is stood on the tracked foot automatically before the fit is applied: its sole is turned down, its toes forward and its big toe to the inside (all read from the shape of the model), it is scaled to a typical shoe length (25 DeepAR foot units, a foot being about 24) and the middle of its sole is put on the sole of the foot, slightly behind the middle of the foot. So the all-zero fit (scale 1, rotation [0, 0, 0], position [0, 0, 0]) is usually close, and a fit only corrects what is left. scale multiplies the size around the middle of the sole (1.05 is 5% bigger); rotation is Euler degrees [x, y, z] around the middle of the sole (x tips the toes up or down, y turns the toes inward or outward, z rolls it onto one side; 180 on y only if the shoe clearly points backwards); position is in foot units as [towards the big toe, up, towards the toes]; stretch is an optional [width, height, length] multiplier. Change values in small steps, e.g. 0.5 to 2 units of position and 2 to 10 degrees of rotation.';

    public static function isConfigured(): bool
    {
        return filled(config('services.n8n.reoptimize_url')) && filled(config('services.n8n.shopar_key'));
    }

    /**
     * Returns why the request failed, or null when n8n accepted it.
     *
     * @param  array{id: string, name: string, fit: array<string, mixed>|null}  $shoe
     */
    public function send(ShoeFitRound $round, array $shoe, bool $usesEffect, ?UploadedFile $screenshot): ?string
    {
        $payload = [
            'round_id' => $round->id,
            'comment' => $round->comment,
            'renderer' => $usesEffect ? self::RENDERER_EFFECT : self::RENDERER_GLB,
            'fit_guide' => $usesEffect ? self::EFFECT_FIT_GUIDE : self::GLB_FIT_GUIDE,
            'shoe' => $shoe,
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
