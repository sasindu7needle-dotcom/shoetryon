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

    /** n8n sent back Tripo's 3D model; it waits for the admin to preview it on their feet and approve it. */
    case ModelReady = 'model_ready';

    /** Approved before shoes could be worn as their GLB; it waits for a DeepAR effect before it joins the library. */
    case ModelApproved = 'model_approved';

    /** The admin rejected the 3D model; the shoe has to be sent again. */
    case Rejected = 'rejected';

    /** The shoe is in the library, hidden until it passes the admin's live try-on. */
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
            self::ReadyForReview => 'Try-on review',
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
     * What the admin has to do next for a shoe in this status, if anything.
     */
    public function nextStep(): ?string
    {
        return match ($this) {
            self::ModelReady => 'Preview it on your feet, then approve or reject the 3D model.',
            self::ModelApproved => 'Build its .deepar effect in DeepAR Studio and upload it.',
            self::ReadyForReview => 'Try it on live, then publish it or tell Claude what to fix.',
            default => null,
        };
    }

    /**
     * Statuses where the shoe is waiting on the admin, most urgent first.
     *
     * @return list<self>
     */
    public static function awaitingAdmin(): array
    {
        return [self::ModelReady, self::ModelApproved, self::ReadyForReview];
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
