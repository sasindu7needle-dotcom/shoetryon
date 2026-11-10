<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

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
     */
    public function show(): View
    {
        $shoes = collect($this->shoes())->sortByDesc('ready')->values()->all();

        return view('tryon', [
            'shoes' => $shoes,
            'licenseKey' => config('services.deepar.license_key'),
            'rootPath' => asset('vendor/deepar').'/',
            'fits' => collect($shoes)->mapWithKeys(fn ($s) => [$s['id'] => $s['fit'] ?? null]),
        ]);
    }

    private function shoes(): array
    {
        return collect(config('shoes'))->map(fn (array $shoe) => [
            ...$shoe,
            'thumb' => asset($shoe['thumb']),
            'glb' => asset($shoe['glb']),
            'effect' => $this->versionedAsset($shoe['effect']),
            'ready' => file_exists(public_path($shoe['effect'])),
        ])->all();
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
