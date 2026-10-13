<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The invoice designs. Each key has a Blade view under resources/views/invoices/templates.
 */
enum InvoiceTemplate: string
{
    case Modern = 'modern';
    case Classic = 'classic';
    case Receipt = 'receipt';
    case Minimal = 'minimal';

    public function label(): string
    {
        return match ($this) {
            self::Modern => 'Modern',
            self::Classic => 'Classic',
            self::Receipt => 'Receipt',
            self::Minimal => 'Minimal',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Modern => 'Navy amount-due panel, service list, payment QR card. Suits freelancers and studios.',
            self::Classic => 'A traditional bordered table with quantity, rate and amount. Suits shops and traders.',
            self::Receipt => 'Narrow, compact digital receipt — reads well on a phone screen.',
            self::Minimal => 'Plain black-on-white with generous spacing.',
        };
    }
}
