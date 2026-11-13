<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shoe;
use Illuminate\Http\RedirectResponse;

class ShoeVisibilityController extends Controller
{
    /**
     * Unhides the shoe, so it shows on the site again.
     */
    public function store(Shoe $shoe): RedirectResponse
    {
        $shoe->update(['is_visible' => true]);

        return back(fallback: route('admin.shoes.index'))->with('status', "{$shoe->name} is visible on the site again.");
    }

    /**
     * Hides the shoe from the site but keeps it in the library.
     */
    public function destroy(Shoe $shoe): RedirectResponse
    {
        $shoe->update(['is_visible' => false]);

        return back(fallback: route('admin.shoes.index'))->with('status', "{$shoe->name} is now hidden from the site.");
    }
}
