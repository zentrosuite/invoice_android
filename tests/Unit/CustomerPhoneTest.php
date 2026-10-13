<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Customer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CustomerPhoneTest extends TestCase
{
    /**
     * @return array<string, array{string|null, string|null, string|null}>
     */
    public static function numbers(): array
    {
        return [
            'plain mobile' => ['9876543210', '9876543210', '919876543210'],
            'spaced with +91' => ['+91 98765 43210', '9876543210', '919876543210'],
            'leading zero' => ['09876543210', '9876543210', '919876543210'],
            'dashes' => ['98765-43210', '9876543210', '919876543210'],
            'foreign' => ['+44 7700 900123', '447700900123', '447700900123'],
            'too short for WhatsApp' => ['2345678', '2345678', null],
            'empty' => ['', null, null],
        ];
    }

    #[DataProvider('numbers')]
    public function test_phone_numbers_normalise(string $typed, ?string $digits, ?string $whatsapp): void
    {
        $this->assertSame($digits, Customer::normalisePhone($typed));
        $this->assertSame($whatsapp, (new Customer(['phone' => $typed]))->whatsappNumber());
    }
}
