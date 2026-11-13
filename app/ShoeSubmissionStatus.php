<?php

namespace App;

/**
 * Where a shoe sent to the n8n "new shoe from photos" workflow has got to.
 */
enum ShoeSubmissionStatus: string
{
    /** n8n accepted the photos and is making the shoe. */
    case Processing = 'processing';

    /** n8n could not be reached, refused the photos, or Tripo could not make the model. */
    case Failed = 'failed';

    /** n8n sent back Tripo's 3D model; it waits for the admin to check it against the photo. */
    case ModelReady = 'model_ready';

    /** The admin approved the 3D model; it waits for its AR effect before it joins the library. */
    case ModelApproved = 'model_approved';

    /** The admin rejected the 3D model; the shoe has to be sent again. */
    case Rejected = 'rejected';

    /** The finished shoe reached the site, hidden until it is approved after a phone test. */
    case ReadyForReview = 'ready_for_review';

    /** The shoe was approved and is visible on the site. */
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Processing => 'Being made',
            self::Failed => 'Failed',
            self::ModelReady => 'Model to review',
            self::ModelApproved => 'Model approved',
            self::Rejected => 'Model rejected',
            self::ReadyForReview => 'Ready for phone test',
            self::Published => 'Published',
        };
    }

    /**
     * Tailwind classes for the status badge.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Processing => 'bg-amber-100 text-amber-800',
            self::Failed => 'bg-rose-100 text-rose-700',
            self::ModelReady => 'bg-sky-100 text-sky-800',
            self::ModelApproved => 'bg-indigo-100 text-indigo-800',
            self::Rejected => 'bg-rose-100 text-rose-700',
            self::ReadyForReview => 'bg-brand-500/10 text-brand-900',
            self::Published => 'bg-emerald-100 text-emerald-700',
        };
    }

    /**
     * Statuses that hold on to the shoe's id, so the same shoe is not sent twice.
     *
     * @return list<self>
     */
    public static function claimingId(): array
    {
        return [self::Processing, self::ModelReady, self::ModelApproved, self::ReadyForReview];
    }
}
