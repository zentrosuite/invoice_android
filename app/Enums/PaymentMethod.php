<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case Upi = 'upi';
    case Cash = 'cash';
    case Bank = 'bank';
    case Card = 'card';
    case Cheque = 'cheque';
    case Other = 'other';
    /** An advance the customer paid earlier, adjusted against this invoice (AdvanceService). */
    case Advance = 'advance';

    public function label(): string
    {
        return match ($this) {
            self::Upi => 'UPI',
            self::Cash => 'Cash',
            self::Bank => 'Bank transfer',
            self::Card => 'Card',
            self::Cheque => 'Cheque',
            self::Other => 'Other',
            self::Advance => 'Advance adjusted',
        };
    }

    /**
     * The ways money can be received: everything but Advance, which is not a way of paying but
     * the use of money already received.
     *
     * @return list<self>
     */
    public static function received(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $m): bool => $m !== self::Advance));
    }
}
