<?php

declare(strict_types=1);

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Amounts as an Indian reader writes them: ₹1,25,000.00, not ₹125,000.00.
 *
 * number_format() only knows groups of three. Indian grouping is three for the last group and
 * two for every group before it.
 */
final class Money
{
    public static function format(string|int|float|null $amount, string $symbol = '₹', bool $decimals = true): string
    {
        $value = BigDecimal::of((string) ($amount ?? '0'))->toScale(2, RoundingMode::HALF_UP);
        $negative = $value->isNegative();
        $value = $value->abs();

        // Always two places after toScale(2), so the string splits cleanly.
        [$whole, $fraction] = explode('.', (string) $value);

        if (strlen($whole) > 3) {
            $last = substr($whole, -3);
            $rest = substr($whole, 0, -3);
            $rest = (string) preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
            $whole = $rest . ',' . $last;
        }

        return ($negative ? '-' : '') . $symbol . $whole . ($decimals ? '.' . $fraction : '');
    }

    /**
     * Whole rupees when there are no paise (₹3,599), otherwise two places (₹3,599.50).
     */
    public static function short(string|int|float|null $amount, string $symbol = '₹'): string
    {
        $value = BigDecimal::of((string) ($amount ?? '0'))->toScale(2, RoundingMode::HALF_UP);

        return self::format($amount, $symbol, ! str_ends_with((string) $value, '.00'));
    }
}
