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

    public function label(): string
    {
        return match ($this) {
            self::Upi => 'UPI',
            self::Cash => 'Cash',
            self::Bank => 'Bank transfer',
            self::Card => 'Card',
            self::Cheque => 'Cheque',
            self::Other => 'Other',
        };
    }
}
