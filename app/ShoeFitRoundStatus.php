<?php

namespace App;

/**
 * Where a "re-optimize with Claude" request for a shoe's AR fit has got to.
 */
enum ShoeFitRoundStatus: string
{
    /** Sent to n8n; Claude is working out a new fit. */
    case Processing = 'processing';

    /** Claude's new fit was saved to the shoe. */
    case Applied = 'applied';

    /** Claude found nothing in the fit to change. */
    case NoChange = 'no_change';

    /** n8n could not be reached, or the workflow stopped. */
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Processing => 'Claude is working',
            self::Applied => 'New fit saved',
            self::NoChange => 'No change',
            self::Failed => 'Failed',
        };
    }

    /**
     * Tailwind classes for the status badge.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Processing => 'bg-amber-100 text-amber-800',
            self::Applied => 'bg-emerald-100 text-emerald-700',
            self::NoChange => 'bg-slate-100 text-slate-700',
            self::Failed => 'bg-rose-100 text-rose-700',
        };
    }
}
