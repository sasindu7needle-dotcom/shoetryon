<?php

namespace App\Http\Controllers\N8n;

use App\Http\Controllers\Controller;
use App\Http\Requests\N8n\StoreShoeModelRequest;
use App\Models\ShoeSubmission;
use App\ShoeSubmissionStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Where the n8n workflow reports back on a shoe sent from the Add new shoe
 * page. The workflow knows the shoe only by its id (slug), which it works
 * out from the name the same way ShoeSubmission::slugFor does.
 */
class ShoeSubmissionCallbackController extends Controller
{
    /**
     * Folder under public/ that holds the GLBs and photos of new shoes.
     */
    public const MODEL_FOLDER = 'models/generated';

    /**
     * Stores Tripo's GLB as public/models/generated/<id>.glb and marks the
     * shoe as ready for the admin to check and give its AR effect.
     */
    public function storeModel(StoreShoeModelRequest $request, string $slug): JsonResponse
    {
        $submission = $this->processingSubmission($slug);

        $glbPath = Storage::disk('shoe_assets')->putFileAs(self::MODEL_FOLDER, $request->file('glb'), $submission->slug.'.glb');

        $submission->update([
            'glb_path' => $glbPath,
            'tripo_task_id' => $request->validated('tripo_task_id'),
            'status' => ShoeSubmissionStatus::ModelReady,
            'message' => 'The 3D model is ready. Check it, then add its DeepAR effect.',
        ]);

        return response()->json(['status' => $submission->status->value]);
    }

    /**
     * Records why the workflow could not make the shoe, so the admin sees it
     * and can send the shoe again.
     */
    public function storeFailure(Request $request, string $slug): JsonResponse
    {
        $validated = $request->validate(['message' => ['required', 'string', 'max:2000']]);

        $submission = $this->processingSubmission($slug);
        $submission->update(['status' => ShoeSubmissionStatus::Failed, 'message' => $validated['message']]);

        return response()->json(['status' => $submission->status->value]);
    }

    /**
     * The latest submission with this id that is still waiting for n8n.
     */
    private function processingSubmission(string $slug): ShoeSubmission
    {
        return ShoeSubmission::query()
            ->where('slug', $slug)
            ->where('status', ShoeSubmissionStatus::Processing)
            ->latest('id')
            ->firstOrFail();
    }
}
