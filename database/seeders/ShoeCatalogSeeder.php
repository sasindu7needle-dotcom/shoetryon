<?php

namespace Database\Seeders;

use App\Models\Shoe;
use Illuminate\Database\Seeder;

class ShoeCatalogSeeder extends Seeder
{
    /**
     * Fills an empty shoe library from config/shoes.php. It leaves an existing
     * library alone, so it is safe to run on every deploy.
     */
    public function run(): void
    {
        if (Shoe::query()->exists()) {
            return;
        }

        foreach (config('shoes') as $index => $shoe) {
            Shoe::query()->create([
                'slug' => $shoe['id'],
                'name' => $shoe['name'],
                'glb' => $shoe['glb'],
                'thumb' => $shoe['thumb'],
                'effect' => $shoe['effect'],
                'fit' => $shoe['fit'] ?? null,
                'position' => $index + 1,
                'is_visible' => true,
            ]);
        }
    }
}
