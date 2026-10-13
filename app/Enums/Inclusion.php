<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A package line's status.
 */
enum Inclusion: string
{
    case Included = 'included';
    case Excluded = 'excluded';

    public function label(): string
    {
        return match ($this) {
            self::Included => 'Included',
            self::Excluded => 'Not included',
        };
    }
}
