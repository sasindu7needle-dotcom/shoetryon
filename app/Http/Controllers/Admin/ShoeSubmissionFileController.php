<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShoeSubmission;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShoeSubmissionFileController extends Controller
{
    /**
     * Sends a submission's front photo or 3D model through Laravel, so the
     * review page shows them whenever the page itself loads, however the
     * web server serves public/.
     */
    public function show(ShoeSubmission $submission, string $file): StreamedResponse
    {
        $path = match ($file) {
            'photo' => $submission->photo_path,
            'model' => $submission->glb_path,
            default => abort(404),
        };

        $disk = Storage::disk('shoe_assets');
        abort_unless($path !== null && $disk->exists($path), 404, 'The file is no longer on the server.');

        $headers = ['Cache-Control' => 'private, max-age=300'];

        if ($file === 'model') {
            $headers['Content-Type'] = 'model/gltf-binary';
        }

        return request()->boolean('download')
            ? $disk->download($path, $submission->slug.'.'.pathinfo($path, PATHINFO_EXTENSION), $headers)
            : $disk->response($path, null, $headers);
    }
}
