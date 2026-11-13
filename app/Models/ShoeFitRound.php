<?php

namespace App\Models;

use App\ShoeFitRoundStatus;
use Database\Factories\ShoeFitRoundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One "re-optimize with Claude" request: the admin's comment on how a shoe
 * sits on the foot, and the fit Claude answered with through n8n.
 */
#[Fillable(['shoe_id', 'user_id', 'comment', 'status', 'previous_fit', 'new_fit', 'message'])]
class ShoeFitRound extends Model
{
    /** @use HasFactory<ShoeFitRoundFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ShoeFitRoundStatus::class,
            'previous_fit' => 'array',
            'new_fit' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Shoe, $this>
     */
    public function shoe(): BelongsTo
    {
        return $this->belongsTo(Shoe::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
