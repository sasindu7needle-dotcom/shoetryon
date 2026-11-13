<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shoe;
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

        return view('admin.dashboard', [
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
