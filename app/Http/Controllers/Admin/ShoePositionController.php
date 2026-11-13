<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shoe;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShoePositionController extends Controller
{
    /**
     * Moves the shoe one place up or down the library.
     */
    public function update(Request $request, Shoe $shoe): RedirectResponse
    {
        $validated = $request->validate([
            'direction' => ['required', Rule::in(['up', 'down'])],
        ]);

        $shoe->moveTo($validated['direction']);

        return back();
    }
}
