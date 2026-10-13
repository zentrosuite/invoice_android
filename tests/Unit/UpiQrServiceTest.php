<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\UpiQrService;
use App\Settings\CompanySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UpiQrServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_upi_id_means_no_qr(): void
    {
        $this->assertNull(app(UpiQrService::class)->forAmount('100', 'INV-0001'));
    }

    public function test_the_payload_carries_payee_amount_and_reference(): void
    {
        $company = app(CompanySettings::class);
        $company->name = 'Rasi & Sons';
        $company->upiId = 'rasi@ybl';
        $company->save();

        $payload = app(UpiQrService::class)->payload('3599', 'INV-0007');

        $this->assertNotNull($payload);
        parse_str((string) parse_url($payload, PHP_URL_QUERY), $query);
        $this->assertSame('rasi@ybl', $query['pa']);
        $this->assertSame('3599.00', $query['am']);
        $this->assertSame('INV-0007', $query['tr']);
        // The ampersand is replaced, not left to split the query string.
        $this->assertSame('Rasi - Sons', $query['pn']);
    }

    public function test_a_zero_amount_gets_no_qr(): void
    {
        $company = app(CompanySettings::class);
        $company->upiId = 'rasi@ybl';
        $company->save();

        $this->assertNull(app(UpiQrService::class)->forAmount('0', 'INV-1'));
        $this->assertStringStartsWith('data:image/svg+xml', (string) app(UpiQrService::class)->forAmount('1', 'INV-1'));
    }
}
