<?php

namespace App\Http\Controllers;

use App\Models\Shoe;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class TryOnController extends Controller
{
    public function home(): View
    {
        $shoes = $this->shoes();

        return view('home', [
            'shoes' => $shoes,
            'shoesJs' => collect($shoes)->map(fn ($s) => [
                'id' => $s['id'], 'name' => $s['name'], 'glb' => $s['glb'],
            ])->values(),
        ]);
    }

    /**
     * Lists the shoes that can be tried on first, so phones show them without swiping.
     * `?preview=<id>` adds that shoe even while it is hidden, listed first: n8n
     * emails this link so a new shoe can be tested on a phone before it is published.
     */
    public function show(Request $request): View
    {
        $shoes = collect($this->shoes())->sortByDesc('ready')->values();
        $previewSlug = $request->string('preview')->toString();

        if ($previewSlug !== '' && ! $shoes->contains('id', $previewSlug)) {
            $previewShoe = Shoe::query()->where('slug', $previewSlug)->first();

            if ($previewShoe !== null) {
                $shoes->prepend([...$this->present($previewShoe), 'name' => $previewShoe->name.' (preview)']);
            }
        }

        $shoes = $shoes->all();

        return view('tryon', [
            'shoes' => $shoes,
            'licenseKey' => config('services.deepar.license_key'),
            'rootPath' => asset('vendor/deepar').'/',
            'trackerEffect' => Shoe::trackerEffectUrl(),
            'fits' => collect($shoes)->mapWithKeys(fn ($s) => [$s['id'] => $s['fit'] ?? null]),
        ]);
    }

    /**
     * The shoes shown on the site, in the order set in the admin shoe library.
     *
     * @return list<array{id: string, name: string, glb: string, thumb: string, effect: ?string, fit: ?array<string, mixed>, legMask: bool, ready: bool}>
     */
    private function shoes(): array
    {
        return Shoe::query()->visible()->ordered()->get()->map(fn (Shoe $shoe) => $this->present($shoe))->all();
    }

    /**
     * A shoe with a DeepAR effect is worn through it; one without (new shoes
     * from n8n) is worn as its GLB, drawn on the tracked feet.
     *
     * @return array{id: string, name: string, glb: string, thumb: string, effect: ?string, fit: ?array<string, mixed>, legMask: bool, ready: bool}
     */
    private function present(Shoe $shoe): array
    {
        return [
            'id' => $shoe->slug,
            'name' => $shoe->name,
            'thumb' => asset($shoe->thumb),
            'glb' => $this->versionedAsset($shoe->glb),
            'effect' => $shoe->usesEffect() ? $this->versionedAsset($shoe->effect) : null,
            'fit' => $shoe->fit,
            'legMask' => $shoe->leg_mask,
            'ready' => $shoe->isArReady(),
        ];
    }

    /**
     * Rebuilt effect files keep their name, and Apache serves them without a
     * Cache-Control header, so phones reuse a cached copy for hours after a
     * deploy while the page already has the new fits. The file's modification
     * time in the URL makes them download the new file.
     */
    private function versionedAsset(string $path): string
    {
        $file = public_path($path);

        return file_exists($file) ? asset($path).'?v='.filemtime($file) : asset($path);
    }
}
