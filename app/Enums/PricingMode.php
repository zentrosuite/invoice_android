<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How an invoice's total is arrived at.
 *
 * Itemised: every line has a price (quantity × rate, or a flat amount) and the lines add up.
 * Package: lines are a list of what the job covers, each Included or Not included, and the
 * invoice carries one agreed price — the freelancer's "website + logo + SEO for ₹3,599".
 */
enum PricingMode: string
{
    case Itemised = 'itemised';
    case Package = 'package';

    public function label(): string
    {
        return match ($this) {
            self::Itemised => 'Itemised',
            self::Package => 'Package',
        };
    }
}
