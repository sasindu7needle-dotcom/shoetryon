<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shoe;
use App\Models\ShoeSubmission;
use App\ShoeSubmissionStatus;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    /**
     * How many visible shoes the dashboard previews; the rest are in the shoe library.
     */
    public const PREVIEW_LIMIT = 8;

    public function __invoke(): View
    {
        $total = Shoe::query()->count();
        $visible = Shoe::query()->visible()->count();

        $awaitingStatuses = ShoeSubmissionStatus::awaitingAdmin();
        $awaitingReview = ShoeSubmission::query()
            ->whereIn('status', $awaitingStatuses)
            ->latest()
            ->get()
            ->sortBy(fn (ShoeSubmission $submission) => array_search($submission->status, $awaitingStatuses, true))
            ->values();

        return view('admin.dashboard', [
            'awaitingReview' => $awaitingReview,
            'visibleShoes' => Shoe::query()->visible()->ordered()->limit(self::PREVIEW_LIMIT)->get(),
            'stats' => [
                'total' => $total,
                'visible' => $visible,
                'hidden' => $total - $visible,
                'arReady' => Shoe::query()->pluck('effect')->filter(fn (string $effect) => file_exists(public_path($effect)))->count(),
            ],
        ]);
    }
}
