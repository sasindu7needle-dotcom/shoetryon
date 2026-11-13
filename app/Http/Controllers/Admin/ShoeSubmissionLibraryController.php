<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AddSubmissionToLibraryRequest;
use App\Models\Shoe;
use App\Models\ShoeSubmission;
use App\ShoeSubmissionStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ShoeSubmissionLibraryController extends Controller
{
    /**
     * Folder under public/ that holds the DeepAR effects of new shoes.
     */
    public const EFFECT_FOLDER = 'effects/generated';

    /**
     * Adds a shoe whose 3D model the admin approved to the library, hidden, with the
     * DeepAR effect the admin built from its GLB in DeepAR Studio. Its fit
     * starts empty, so the try-on keeps the placement Studio exported.
     */
    public function store(AddSubmissionToLibraryRequest $request, ShoeSubmission $submission): RedirectResponse
    {
        abort_unless($submission->status === ShoeSubmissionStatus::ModelApproved, 409, 'This shoe has no approved 3D model waiting for its effect.');

        if (Shoe::query()->where('slug', $submission->slug)->exists()) {
            return back()->with('error', "The library already has a shoe with the id “{$submission->slug}”.");
        }

        $effectPath = Storage::disk('shoe_assets')->putFileAs(self::EFFECT_FOLDER, $request->file('effect'), $submission->slug.'.deepar');

        $shoe = DB::transaction(function () use ($submission, $effectPath) {
            $shoe = Shoe::query()->create([
                'slug' => $submission->slug,
                'name' => $submission->name,
                'glb' => $submission->glb_path,
                'thumb' => $submission->photo_path,
                'effect' => $effectPath,
                'fit' => null,
                'is_visible' => false,
            ]);

            $submission->update([
                'status' => ShoeSubmissionStatus::ReadyForReview,
                'message' => 'In the shoe library, hidden until the phone test is approved.',
            ]);

            return $shoe;
        });

        return redirect()->route('admin.shoe-submissions.show', $submission)->with('status', "{$shoe->name} was added to the library, hidden. Try it on live, then publish it or tell Claude what to fix.");
    }
}
