<?php

namespace App;

use App\Models\Shoe;
use App\Models\ShoeSubmission;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Turns Tripo's GLB into a DeepAR effect (scripts/build-deepar-effect.py
 * --auto), so DeepAR itself draws the shoe on the feet it tracks, the same
 * way as the shoes built in DeepAR Studio. The script stands the model on the
 * foot from its own shape (sole down, toes forward, left or right) and
 * reports the fit that places it, which becomes the preview's starting fit.
 */
class ShoeEffectBuilder
{
    /**
     * Folder under public/ that holds the effects built from Tripo's GLBs.
     */
    public const EFFECT_FOLDER = 'effects/generated';

    /**
     * Builds the effect and returns why it failed, or null when the shoe now
     * has one. A shoe without an effect is still previewed and worn as its GLB.
     */
    public function build(ShoeSubmission $submission): ?string
    {
        $disk = Storage::disk('shoe_assets');

        if ($submission->glb_path === null || ! $disk->exists($submission->glb_path)) {
            return $this->fail($submission, 'The shoe has no 3D model to build an effect from.');
        }

        $settings = config('services.deepar.effect_builder');
        $effectPath = self::EFFECT_FOLDER.'/'.$submission->slug.'.deepar';
        $disk->makeDirectory(self::EFFECT_FOLDER);

        try {
            $result = Process::timeout($settings['timeout'])->path(base_path())->run([
                $settings['python'],
                base_path('scripts/build-deepar-effect.py'),
                public_path($settings['template']),
                $disk->path($submission->glb_path),
                $disk->path($effectPath),
                $submission->slug,
                '--auto',
            ]);
        } catch (ProcessTimedOutException) {
            return $this->fail($submission, "Building the effect took longer than {$settings['timeout']} seconds.");
        }

        $answer = $this->fitFrom($result->output());

        if ($result->failed() || $answer === null || ! $disk->exists($effectPath)) {
            $disk->delete($effectPath);
            Log::warning('Could not build a DeepAR effect from a GLB.', [
                'submission' => $submission->id,
                'exit_code' => $result->exitCode(),
                'error' => Str::limit($result->errorOutput(), 1000),
            ]);

            return $this->fail($submission, $this->reasonFrom($result->errorOutput()));
        }

        $previewFit = [
            'fit' => $answer['fit'],
            'leg_mask' => (bool) ($submission->preview_fit['leg_mask'] ?? true),
            'orientation' => $answer['orientation'] ?? null,
        ];

        $submission->update(['effect_path' => $effectPath, 'effect_error' => null, 'preview_fit' => $previewFit]);
        $this->updateLibraryShoe($submission);

        return null;
    }

    /**
     * An approved shoe that is still worn as its GLB switches to the effect.
     */
    private function updateLibraryShoe(ShoeSubmission $submission): void
    {
        Shoe::query()
            ->where('slug', $submission->slug)
            ->whereNull('effect')
            ->update(['effect' => $submission->effect_path, 'fit' => $submission->preview_fit['fit']]);
    }

    /**
     * The script's last line: `FIT {"fit": {...}, "orientation": {...}}`.
     *
     * @return array{fit: array{scale: float, rotation: list<float>, position: list<float>}, orientation?: array<string, mixed>}|null
     */
    private function fitFrom(string $output): ?array
    {
        $line = collect(preg_split('/\R/', $output))->last(fn (string $line) => str_starts_with($line, 'FIT '));
        $answer = $line === null ? null : json_decode(substr($line, 4), true);

        return is_array($answer['fit'] ?? null) ? $answer : null;
    }

    /**
     * The script explains what it can't handle on an `ERROR ...` line; any
     * other failure (Python or Pillow missing, a crash) is summed up.
     */
    private function reasonFrom(string $errorOutput): string
    {
        $line = collect(preg_split('/\R/', trim($errorOutput)))->last(fn (string $line) => str_starts_with($line, 'ERROR '));

        if ($line !== null) {
            return 'The effect could not be built: '.substr($line, 6).'.';
        }

        $lastLine = Str::limit((string) collect(preg_split('/\R/', trim($errorOutput)))->last(), 300);

        return 'The effect could not be built'.($lastLine !== '' ? ": {$lastLine}" : '.').' Check that Python 3 and Pillow are installed on the server.';
    }

    private function fail(ShoeSubmission $submission, string $reason): string
    {
        $submission->update(['effect_path' => null, 'effect_error' => $reason]);

        return $reason;
    }
}
