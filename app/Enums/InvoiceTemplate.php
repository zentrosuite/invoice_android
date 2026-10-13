<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The invoice designs. Each key has a Blade view under resources/views/invoices/templates.
 *
 * Package is the odd one out: it is a pricing model as much as a look. Its lines carry an
 * Included / Not included status instead of quantity × rate, and the invoice has a single
 * package total — the way a freelancer quotes "website + logo + SEO for ₹3,599". Every other
 * design is itemised.
 */
enum InvoiceTemplate: string
{
    case Package = 'package';
    case Modern = 'modern';
    case Classic = 'classic';
    case Receipt = 'receipt';
    case Minimal = 'minimal';

    public function label(): string
    {
        return match ($this) {
            self::Package => 'Package',
            self::Modern => 'Modern',
            self::Classic => 'Classic',
            self::Receipt => 'Receipt',
            self::Minimal => 'Minimal',
        };
    }

    /**
     * Whether lines are priced individually (quantity × rate) or covered by one package total.
     */
    public function isPackage(): bool
    {
        return $this === self::Package;
    }

    public function description(): string
    {
        return match ($this) {
            self::Package => 'Services marked Included or Not included, with one final package total. Suits freelancers and studios.',
            self::Modern => 'Navy amount-due panel with an itemised list and a payment QR card.',
            self::Classic => 'A traditional bordered table with quantity, rate and amount. Suits shops and traders.',
            self::Receipt => 'Narrow, compact digital receipt — reads well on a phone screen.',
            self::Minimal => 'Plain black-on-white with generous spacing.',
        };
    }
}
