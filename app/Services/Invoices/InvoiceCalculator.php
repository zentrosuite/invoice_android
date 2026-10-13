<?php

declare(strict_types=1);

namespace App\Services\Invoices;

use App\Enums\PricingMode;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Works out every figure on an invoice from its inputs, in exact decimal arithmetic.
 *
 * The browser shows the same sums live while typing, but only these numbers are stored. A
 * float never touches money here: 0.1 + 0.2 must be 0.30, and on a ₹1,00,000 invoice the
 * difference is a paisa the customer will ask about.
 *
 *   itemised:  line amount = quantity × rate (or the flat amount when there is no quantity)
 *              subtotal    = Σ line amounts
 *   package:   subtotal    = the package price; lines carry no money
 *   both:      discount    = amount, or % of subtotal — never more than the subtotal
 *              tax         = tax % of (subtotal − discount)
 *              total       = subtotal − discount + tax
 */
final class InvoiceCalculator
{
    /**
     * @param  list<array{quantity?: string|null, rate?: string|null}>  $lines
     * @return array{
     *     lines: list<array{quantity: string|null, rate: string|null, amount: string}>,
     *     subtotal: string, discount_amount: string, tax_amount: string, total: string,
     * }
     */
    public function calculate(
        PricingMode $mode,
        array $lines,
        bool $showQuantity,
        ?string $packageTotal,
        ?string $discountType,
        ?string $discountValue,
        ?string $taxPercent,
    ): array {
        $computed = [];
        $subtotal = BigDecimal::zero();

        foreach ($lines as $line) {
            if ($mode === PricingMode::Package) {
                $computed[] = ['quantity' => null, 'rate' => null, 'amount' => '0.00'];

                continue;
            }

            $rate = $this->money($line['rate'] ?? null);

            if ($showQuantity) {
                $quantity = $this->decimal($line['quantity'] ?? null, 3) ?? BigDecimal::one()->toScale(3);
                $amount = $quantity->multipliedBy($rate)->toScale(2, RoundingMode::HALF_UP);
            } else {
                $quantity = null;
                $amount = $rate;
            }

            $subtotal = $subtotal->plus($amount);
            $computed[] = [
                'quantity' => $quantity === null ? null : (string) $quantity,
                'rate' => (string) $rate,
                'amount' => (string) $amount,
            ];
        }

        if ($mode === PricingMode::Package) {
            $subtotal = $this->money($packageTotal);
        }

        $discount = BigDecimal::zero()->toScale(2);
        $value = $this->decimal($discountValue, 2);

        if ($value !== null && $value->isPositive()) {
            $discount = $discountType === 'percent'
                ? $subtotal->multipliedBy(BigDecimal::min($value, BigDecimal::of(100)))->dividedBy(100, 2, RoundingMode::HALF_UP)
                : BigDecimal::min($value, $subtotal);
        }

        $taxable = $subtotal->minus($discount);
        $rate = $this->decimal($taxPercent, 2);
        $tax = ($rate !== null && $rate->isPositive())
            ? $taxable->multipliedBy($rate)->dividedBy(100, 2, RoundingMode::HALF_UP)
            : BigDecimal::zero()->toScale(2);

        return [
            'lines' => $computed,
            'subtotal' => (string) $subtotal->toScale(2),
            'discount_amount' => (string) $discount->toScale(2),
            'tax_amount' => (string) $tax->toScale(2),
            'total' => (string) $taxable->plus($tax)->toScale(2),
        ];
    }

    /**
     * A non-negative amount at 2 places; blank or junk is zero.
     */
    private function money(?string $value): BigDecimal
    {
        return $this->decimal($value, 2) ?? BigDecimal::zero()->toScale(2);
    }

    private function decimal(?string $value, int $scale): ?BigDecimal
    {
        $value = str_replace([',', ' '], '', trim((string) $value));

        if ($value === '' || ! is_numeric($value)) {
            return null;
        }

        $decimal = BigDecimal::of($value)->toScale($scale, RoundingMode::HALF_UP);

        return $decimal->isNegative() ? BigDecimal::zero()->toScale($scale) : $decimal;
    }
}
