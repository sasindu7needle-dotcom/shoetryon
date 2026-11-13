<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateShoeRequest;
use App\Models\Shoe;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ShoeController extends Controller
{
    /**
     * @var list<int>
     */
    public const PER_PAGE_OPTIONS = [10, 25, 50];

    /**
     * The library one page at a time, optionally narrowed to visible or hidden
     * shoes or to a name search. A page past the end (after deleting the last
     * shoe on it, say) goes to the last page instead.
     */
    public function index(Request $request): View|RedirectResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['visible', 'hidden'])],
            'search' => ['nullable', 'string', 'max:80'],
            'per_page' => ['nullable', 'integer', Rule::in(self::PER_PAGE_OPTIONS)],
        ]);

        $status = $filters['status'] ?? null;
        $search = trim($filters['search'] ?? '');
        $perPage = (int) ($filters['per_page'] ?? self::PER_PAGE_OPTIONS[0]);

        $shoes = Shoe::query()
            ->when($status === 'visible', fn ($query) => $query->where('is_visible', true))
            ->when($status === 'hidden', fn ($query) => $query->where('is_visible', false))
            ->when($search !== '', function ($query) use ($search) {
                $pattern = '%'.Str::of($search)->replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_']).'%';

                $query->where(fn ($query) => $query->whereLike('name', $pattern)->orWhereLike('slug', $pattern));
            })
            ->ordered()
            ->paginate($perPage)
            ->withQueryString();

        if ($shoes->currentPage() > 1 && $shoes->isEmpty()) {
            return redirect()->to($shoes->url($shoes->lastPage()));
        }

        $counts = Shoe::query()
            ->selectRaw('count(*) as total')
            ->selectRaw('count(case when is_visible = ? then 1 end) as visible', [true])
            ->first();

        return view('admin.shoes.index', [
            'shoes' => $shoes,
            'status' => $status,
            'search' => $search,
            'perPage' => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'isFiltered' => $status !== null || $search !== '',
            'lastPosition' => (int) Shoe::query()->max('position'),
            'counts' => [
                'all' => (int) $counts->total,
                'visible' => (int) $counts->visible,
                'hidden' => (int) $counts->total - (int) $counts->visible,
            ],
        ]);
    }

    public function edit(Request $request, Shoe $shoe): View
    {
        return view('admin.shoes.edit', [
            'shoe' => $shoe,
            'shoeCount' => Shoe::query()->count(),
            'libraryUrl' => UpdateShoeRequest::safeLibraryUrl(url()->previous()),
        ]);
    }

    public function update(UpdateShoeRequest $request, Shoe $shoe): RedirectResponse
    {
        $shoe->update($request->shoeAttributes());

        if ($request->integer('position') !== $shoe->position) {
            $shoe->moveToPosition($request->integer('position'));
        }

        return redirect()->to($request->libraryUrl())->with('status', "{$shoe->name} was updated.");
    }

    /**
     * Removes the shoe from the library and closes the gap it leaves in the
     * order. Its model and effect files stay in public/.
     */
    public function destroy(Shoe $shoe): RedirectResponse
    {
        $shoe->delete();
        Shoe::normalizePositions();

        return back(fallback: route('admin.shoes.index'))->with('status', "{$shoe->name} was deleted.");
    }
}
