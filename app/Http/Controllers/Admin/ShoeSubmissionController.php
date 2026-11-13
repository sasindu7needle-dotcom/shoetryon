<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\N8n\ShoeSubmissionCallbackController;
use App\Http\Requests\Admin\ReviewShoeSubmissionRequest;
use App\Http\Requests\Admin\StoreShoeSubmissionRequest;
use App\Models\Shoe;
use App\Models\ShoeSubmission;
use App\ShoeSubmissionStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ShoeSubmissionController extends Controller
{
    /**
     * The n8n form's dropdown options. Its "Prepare shoe" step only checks
     * whether the chosen text starts with "boot".
     *
     * @var array<string, string>
     */
    public const SHOE_TYPES = [
        'low' => 'Low shoe (sneaker, flat, loafer, sandal)',
        'boot' => 'Boot (covers the ankle)',
    ];

    /**
     * The Add new shoe form, with the shoes sent so far.
     */
    public function create(): View
    {
        return view('admin.shoe-submissions.create', [
            'shoeTypes' => self::SHOE_TYPES,
            'photos' => StoreShoeSubmissionRequest::PHOTOS,
            'requiredPhoto' => StoreShoeSubmissionRequest::REQUIRED_PHOTO,
            'isWorkflowConfigured' => filled(config('services.n8n.new_shoe_form_url')),
            'maxPhotoBytes' => min(StoreShoeSubmissionRequest::MAX_PHOTO_KILOBYTES * 1024, (int) UploadedFile::getMaxFilesize()),
            'maxTotalBytes' => $this->postMaxBytes(),
            'submissions' => ShoeSubmission::query()->with('user')->latest('id')->paginate(10),
        ]);
    }

    /**
     * A sent shoe: its photo, its 3D model once n8n has sent it back, the
     * model review, and the form for its DeepAR effect once approved.
     */
    public function show(ShoeSubmission $submission): View
    {
        $disk = Storage::disk('shoe_assets');

        return view('admin.shoe-submissions.show', [
            'submission' => $submission->load('user', 'reviewer'),
            'hasPhoto' => $submission->photo_path !== null && $disk->exists($submission->photo_path),
            'hasModel' => $submission->glb_path !== null && $disk->exists($submission->glb_path),
            'reviewChecks' => ReviewShoeSubmissionRequest::CHECKS,
            'libraryShoe' => Shoe::query()->where('slug', $submission->slug)->first(),
        ]);
    }

    /**
     * Keeps the front photo as the shoe's thumbnail, then sends the name,
     * type, length and photos to the n8n workflow's form. The workflow has
     * Tripo make the 3D model and sends the GLB back to
     * /api/n8n/shoe-submissions/<id>/model.
     */
    public function store(StoreShoeSubmissionRequest $request): RedirectResponse
    {
        abort_unless(filled(config('services.n8n.new_shoe_form_url')), 503, 'The n8n workflow address is not set.');

        $submission = ShoeSubmission::query()->create([
            'user_id' => $request->user()->id,
            'name' => trim($request->string('name')->toString()),
            'slug' => $request->slug(),
            'shoe_type' => $request->string('shoe_type')->toString(),
            'length_cm' => $request->input('length_cm'),
            'status' => ShoeSubmissionStatus::Processing,
        ]);

        $submission->update(['photo_path' => $this->keepFrontPhoto($request->file(StoreShoeSubmissionRequest::REQUIRED_PHOTO), $submission)]);

        $failure = $this->sendToWorkflow($request, $submission);

        if ($failure !== null) {
            $submission->update(['status' => ShoeSubmissionStatus::Failed, 'message' => $failure]);

            return back()->withInput()->with('error', "{$submission->name} could not be sent to n8n. {$failure}");
        }

        $submission->update(['message' => 'Sent to n8n.']);

        return redirect()->route('admin.shoe-submissions.create')->with('status', "{$submission->name} was sent to n8n. Its 3D model shows up below when Tripo has made it, usually in 1 to 3 minutes. Reload the page to check.");
    }

    /**
     * Saves the front photo as public/models/generated/<id>.<ext>, the
     * thumbnail the shoe gets when it joins the library.
     */
    private function keepFrontPhoto(UploadedFile $photo, ShoeSubmission $submission): string
    {
        return Storage::disk('shoe_assets')->putFileAs(ShoeSubmissionCallbackController::MODEL_FOLDER, $photo, $submission->slug.'.'.$photo->extension());
    }

    /**
     * Posts the submission as the n8n form would: n8n reads form fields by
     * their position (`field-0`, `field-1`, …), in the order the form node
     * lists them. Returns why it failed, or null when n8n accepted it.
     */
    private function sendToWorkflow(StoreShoeSubmissionRequest $request, ShoeSubmission $submission): ?string
    {
        $fields = [
            'field-0' => $submission->name,
            'field-1' => self::SHOE_TYPES[$submission->shoe_type],
            'field-2' => (string) $submission->length_cm,
        ];

        // The form node's "Ignore bots" option turns away clients that look like scripts.
        $client = Http::timeout((int) config('services.n8n.timeout'))
            ->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) ShopAR admin uploader')
            ->acceptJson();

        if (filled(config('services.n8n.form_username'))) {
            $client = $client->withBasicAuth((string) config('services.n8n.form_username'), (string) config('services.n8n.form_password'));
        }

        // Optional photos the admin left out are not sent; each sent photo keeps its form position.
        foreach (array_keys(StoreShoeSubmissionRequest::PHOTOS) as $offset => $photoField) {
            $photo = $request->file($photoField);

            if ($photo === null) {
                continue;
            }

            $client = $client->attach('field-'.(3 + $offset), $photo->get(), $submission->slug.'-'.$photoField.'.'.$photo->extension(), ['Content-Type' => $photo->getMimeType()]);
        }

        try {
            $response = $client->post((string) config('services.n8n.new_shoe_form_url'), $fields);
        } catch (ConnectionException $exception) {
            Log::warning('Could not reach the n8n new-shoe form.', ['submission' => $submission->id, 'error' => $exception->getMessage()]);

            return 'n8n could not be reached. Check that it is running and that N8N_NEW_SHOE_FORM_URL is right.';
        }

        if ($response->successful()) {
            return null;
        }

        Log::warning('The n8n new-shoe form refused a submission.', ['submission' => $submission->id, 'status' => $response->status(), 'body' => mb_substr($response->body(), 0, 500)]);

        return $this->describeRefusal($response);
    }

    /**
     * PHP's post_max_size in bytes (0 means no limit).
     */
    private function postMaxBytes(): int
    {
        $value = trim((string) ini_get('post_max_size'));
        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    private function describeRefusal(Response $response): string
    {
        return match ($response->status()) {
            401, 403 => 'n8n refused the login. Check N8N_FORM_USERNAME and N8N_FORM_PASSWORD.',
            404 => 'n8n has no form at that address. Publish the workflow and use its production form URL.',
            413 => 'n8n refused the photos as too large.',
            default => "n8n answered with an error (HTTP {$response->status()}).",
        };
    }
}
