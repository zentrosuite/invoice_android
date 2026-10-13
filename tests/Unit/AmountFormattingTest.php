<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Invoices\AmountInWords;
use App\Support\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AmountFormattingTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function words(): array
    {
        return [
            'the 5 Star invoice' => ['3599', 'Three Thousand Five Hundred Ninety-Nine Rupees Only'],
            'zero' => ['0', 'Zero Rupees Only'],
            'teens' => ['15', 'Fifteen Rupees Only'],
            'lakh' => ['150000', 'One Lakh Fifty Thousand Rupees Only'],
            'crore' => ['12345678', 'One Crore Twenty-Three Lakh Forty-Five Thousand Six Hundred Seventy-Eight Rupees Only'],
            'paise' => ['899.50', 'Eight Hundred Ninety-Nine Rupees and Fifty Paise Only'],
        ];
    }

    #[DataProvider('words')]
    public function test_amount_in_words(string $amount, string $expected): void
    {
        $this->assertSame($expected, (new AmountInWords)->convert($amount));
    }

    public function test_non_rupee_currencies_read_as_plain_numbers(): void
    {
        $this->assertSame('', AmountInWords::currencyWordFor('$'));
        $this->assertSame('Twelve Only', (new AmountInWords)->convert('12', ''));
    }

    public function test_indian_digit_grouping(): void
    {
        $this->assertSame('₹999.00', Money::format('999'));
        $this->assertSame('₹1,25,000.00', Money::format('125000'));
        $this->assertSame('₹1,23,45,678.50', Money::format('12345678.5'));
        $this->assertSame('₹3,599', Money::short('3599'));
        $this->assertSame('₹3,599.50', Money::short('3599.5'));
    }
}
