<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where an invoice is in its life.
 *
 *   draft ──► sent ──► partial ──► paid
 *     ▲         │   (payments move it right; removing them moves it back)
 *     └── cancelled
 *
 * Partial and Paid are not chosen, they are worked out from the payments recorded against the
 * invoice (PaymentService). Every status stays editable: the owner is one person correcting
 * their own bill. A UPI QR is printed only while there is money to collect.
 */
enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Partial = 'partial';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Sent => 'Unpaid',
            self::Partial => 'Part paid',
            self::Paid => 'Paid',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Velzon badge classes.
     */
    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'bg-dark-subtle text-body',
            self::Sent => 'bg-warning-subtle text-warning',
            self::Partial => 'bg-info-subtle text-info',
            self::Paid => 'bg-success-subtle text-success',
            self::Cancelled => 'bg-danger-subtle text-danger',
        };
    }

    public function isPayable(): bool
    {
        return $this === self::Draft || $this === self::Sent || $this === self::Partial;
    }
}
