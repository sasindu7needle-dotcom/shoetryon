<?php

namespace App\Models;

use App\ShoeSubmissionStatus;
use Database\Factories\ShoeSubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Normalizer;

/**
 * A new shoe the admin sent to the n8n workflow as one to four photos.
 */
#[Fillable(['user_id', 'name', 'slug', 'shoe_type', 'length_cm', 'photo_path', 'glb_path', 'tripo_task_id', 'status', 'message'])]
class ShoeSubmission extends Model
{
    /** @use HasFactory<ShoeSubmissionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'length_cm' => 'decimal:1',
            'status' => ShoeSubmissionStatus::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The shoe id the n8n workflow will give this name. It must match the
     * workflow's "Prepare shoe" step exactly, since the finished files and
     * catalog entry are named after it.
     */
    public static function slugFor(string $name): string
    {
        $slug = mb_strtolower(trim($name));
        $slug = class_exists(Normalizer::class) ? (string) Normalizer::normalize($slug, Normalizer::FORM_KD) : $slug;
        $slug = (string) preg_replace('/\p{Mn}+/u', '', $slug);
        $slug = (string) preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        return rtrim(substr($slug, 0, 40), '-');
    }
}
