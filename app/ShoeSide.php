<?php

namespace App;

/**
 * Which foot the shoe in the admin's photos is for. Tripo's model is of that
 * one shoe, so this says whether it must be mirrored to make the other foot's
 * shoe. DeepAR's effect wears a right shoe on the right foot and its mirror on
 * the left, so a left shoe is mirrored into a right one when it is built.
 */
enum ShoeSide: string
{
    case Left = 'left';

    case Right = 'right';

    /**
     * The words the admin picks from on the Add new shoe form.
     */
    public function label(): string
    {
        return match ($this) {
            self::Left => 'Left shoe',
            self::Right => 'Right shoe',
        };
    }

    public function opposite(): self
    {
        return $this === self::Left ? self::Right : self::Left;
    }
}
