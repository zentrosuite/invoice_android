<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\PricingMode;
use App\Services\Invoices\InvoiceCalculator;
use PHPUnit\Framework\TestCase;

final class InvoiceCalculatorTest extends TestCase
{
    private InvoiceCalculator $calc;

    protected function setUp(): void
    {
        $this->calc = new InvoiceCalculator;
    }

    public function test_quantity_times_rate_adds_up(): void
    {
        $r = $this->calc->calculate(PricingMode::Itemised, [
            ['quantity' => '2', 'rate' => '1250'],
            ['quantity' => '1.5', 'rate' => '333.33'],
            ['quantity' => '', 'rate' => '100'], // blank quantity means one
        ], true, null, null, null, null);

        $this->assertSame(['2500.00', '500.00', '100.00'], array_column($r['lines'], 'amount'));
        $this->assertSame('3100.00', $r['subtotal']);
        $this->assertSame('3100.00', $r['total']);
    }

    public function test_floats_do_not_creep_in(): void
    {
        $r = $this->calc->calculate(PricingMode::Itemised, [
            ['rate' => '0.10'], ['rate' => '0.20'],
        ], false, null, null, null, null);

        $this->assertSame('0.30', $r['total']);
        $this->assertNull($r['lines'][0]['quantity']);
    }

    public function test_package_total_is_the_subtotal_and_lines_carry_no_money(): void
    {
        $r = $this->calc->calculate(PricingMode::Package, [
            ['quantity' => '5', 'rate' => '999'],
        ], true, '3599', null, null, null);

        $this->assertSame('0.00', $r['lines'][0]['amount']);
        $this->assertSame('3599.00', $r['subtotal']);
        $this->assertSame('3599.00', $r['total']);
    }

    public function test_discount_then_tax(): void
    {
        $r = $this->calc->calculate(PricingMode::Itemised, [['rate' => '1000']], false, null, 'percent', '10', '18');

        $this->assertSame('100.00', $r['discount_amount']);
        $this->assertSame('162.00', $r['tax_amount']); // 18% of 900
        $this->assertSame('1062.00', $r['total']);
    }

    public function test_a_discount_never_exceeds_the_subtotal(): void
    {
        $r = $this->calc->calculate(PricingMode::Itemised, [['rate' => '500']], false, null, 'amount', '800', null);

        $this->assertSame('500.00', $r['discount_amount']);
        $this->assertSame('0.00', $r['total']);
    }

    public function test_grouped_and_junk_input_is_tolerated(): void
    {
        $r = $this->calc->calculate(PricingMode::Itemised, [['rate' => '1,25,000'], ['rate' => 'abc'], ['rate' => '-50']], false, null, null, null, null);

        $this->assertSame('125000.00', $r['total']);
    }
}
