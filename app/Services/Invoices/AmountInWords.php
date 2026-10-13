<?php

declare(strict_types=1);

namespace App\Services\Invoices;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * "Three Thousand Five Hundred Ninety-Nine Rupees Only".
 *
 * Indian grouping — thousand, lakh, crore — because that is how an amount is read out here:
 * 1,50,000 is "One Lakh Fifty Thousand", never "One Hundred Fifty Thousand".
 */
final class AmountInWords
{
    private const ONES = [
        '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
        'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen',
    ];

    private const TENS = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    /**
     * @param  string  $currency  The plural currency word, or '' for a symbol that is not the rupee.
     */
    public function convert(string $amount, string $currency = 'Rupees', string $fraction = 'Paise'): string
    {
        $value = BigDecimal::of($amount)->abs()->toScale(2, RoundingMode::HALF_UP);
        [$whole, $decimals] = explode('.', (string) $value);
        $paise = (int) $decimals;

        $words = $whole === '0' ? 'Zero' : $this->whole((int) $whole);
        $out = trim($words . ($currency !== '' ? ' ' . $currency : ''));

        if ($paise > 0) {
            $out .= ' and ' . $this->belowHundred($paise) . ($currency !== '' ? ' ' . $fraction : '');
        }

        return $out . ' Only';
    }

    /**
     * The currency word for a symbol: only the rupee is named, anything else reads as a number.
     */
    public static function currencyWordFor(string $symbol): string
    {
        return in_array(trim($symbol), ['₹', 'Rs', 'Rs.', 'INR'], true) ? 'Rupees' : '';
    }

    private function whole(int $number): string
    {
        $parts = [];

        foreach ([[10000000, 'Crore'], [100000, 'Lakh'], [1000, 'Thousand'], [100, 'Hundred']] as [$unit, $name]) {
            if ($number >= $unit) {
                $count = intdiv($number, $unit);
                // Crores can exceed 99 ("One Hundred Twenty Crore"), so recurse rather than look up.
                $parts[] = ($unit === 10000000 ? $this->whole($count) : $this->belowHundred($count)) . ' ' . $name;
                $number %= $unit;
            }
        }

        if ($number > 0) {
            $parts[] = $this->belowHundred($number);
        }

        return implode(' ', $parts);
    }

    private function belowHundred(int $number): string
    {
        if ($number < 20) {
            return self::ONES[$number];
        }

        $tens = self::TENS[intdiv($number, 10)];
        $ones = $number % 10;

        return $ones === 0 ? $tens : $tens . '-' . self::ONES[$ones];
    }
}
