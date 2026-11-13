<?php

namespace App\Models;

use Database\Factories\ShoeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

#[Fillable(['slug', 'name', 'glb', 'thumb', 'effect', 'fit', 'position', 'is_visible'])]
class Shoe extends Model
{
    /** @use HasFactory<ShoeFactory> */
    use HasFactory;

    /**
     * New shoes without a position go to the end of the library.
     */
    protected static function booted(): void
    {
        static::creating(function (Shoe $shoe) {
            if (! $shoe->position) {
                $shoe->position = static::nextPosition();
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fit' => 'array',
            'position' => 'integer',
            'is_visible' => 'boolean',
        ];
    }

    /**
     * Shoes in the order the admin arranged them.
     *
     * @param  Builder<Shoe>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('id');
    }

    /**
     * Shoes shown on the public site.
     *
     * @param  Builder<Shoe>  $query
     */
    #[Scope]
    protected function visible(Builder $query): void
    {
        $query->where('is_visible', true);
    }

    /**
     * Whether the DeepAR effect file exists, so the shoe can be worn in AR.
     */
    public function isArReady(): bool
    {
        return file_exists(public_path($this->effect));
    }

    /**
     * Moves the shoe one place up or down the library by swapping positions
     * with its neighbour, so only two rows change however big the library is.
     */
    public function moveTo(string $direction): void
    {
        DB::transaction(function () use ($direction) {
            $this->ensureUniquePosition();

            $neighbour = static::query()
                ->where('position', $direction === 'up' ? '<' : '>', $this->position)
                ->orderBy('position', $direction === 'up' ? 'desc' : 'asc')
                ->lockForUpdate()
                ->first();

            if ($neighbour === null) {
                return;
            }

            [$this->position, $neighbour->position] = [$neighbour->position, $this->position];
            $this->save();
            $neighbour->save();
        });
    }

    /**
     * Puts the shoe at the given place in the library (1 is first) and shifts
     * the shoes in between. Places past the end put it last.
     */
    public function moveToPosition(int $targetPosition): void
    {
        DB::transaction(function () use ($targetPosition) {
            $orderedIds = static::query()->ordered()->lockForUpdate()->pluck('id')->all();
            $orderedIds = array_values(array_diff($orderedIds, [$this->id]));
            $targetIndex = max(0, min($targetPosition - 1, count($orderedIds)));

            array_splice($orderedIds, $targetIndex, 0, [$this->id]);

            static::reorder($orderedIds);
        });

        $this->refresh();
    }

    /**
     * Numbers the library 1, 2, 3… in its current order, closing gaps left by deleted shoes.
     */
    public static function normalizePositions(): void
    {
        static::reorder(static::query()->ordered()->pluck('id')->all());
    }

    /**
     * The position for a shoe added to the end of the library.
     */
    public static function nextPosition(): int
    {
        return (int) static::query()->max('position') + 1;
    }

    /**
     * Stores the given order, numbering the shoes from 1. Only rows whose
     * number changes are written.
     *
     * @param  list<int>  $orderedIds
     */
    public static function reorder(array $orderedIds): void
    {
        $currentPositions = static::query()->whereKey($orderedIds)->pluck('position', 'id');

        foreach (array_values($orderedIds) as $index => $id) {
            if ($currentPositions[$id] !== $index + 1) {
                static::query()->whereKey($id)->update(['position' => $index + 1]);
            }
        }
    }

    /**
     * Swapping needs every shoe to have its own position; renumbers the library when two share one.
     */
    private function ensureUniquePosition(): void
    {
        if (static::query()->where('position', $this->position)->count() > 1) {
            static::normalizePositions();
            $this->refresh();
        }
    }
}
