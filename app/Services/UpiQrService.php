<?php

declare(strict_types=1);

namespace App\Services;

use App\Settings\CompanySettings;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;

/**
 * The UPI payment QR printed on an unpaid invoice. Ported from the inventory app.
 *
 * Any UPI app (GPay, PhonePe, Paytm, BHIM) reads the upi://pay URI, opens with the payee and the
 * amount already filled in, and the customer only has to confirm.
 */
final readonly class UpiQrService
{
    public function __construct(
        private CompanySettings $company,
    ) {}

    /**
     * An SVG data URI for this amount, or null when there is no UPI ID or nothing to pay.
     *
     * @param  numeric-string  $amount
     */
    public function forAmount(string $amount, string $reference, ?string $note = null, int $size = 220): ?string
    {
        $payload = $this->payload($amount, $reference, $note);

        return $payload === null ? null : $this->render($payload, $size);
    }

    /**
     * The upi://pay URI, or null when there is no VPA or the amount is not positive.
     *
     * Also usable as a tap-to-pay link on a phone, where the QR cannot be scanned by the same
     * device that shows it.
     *
     * @param  numeric-string  $amount
     */
    public function payload(string $amount, string $reference, ?string $note = null): ?string
    {
        $vpa = trim((string) $this->company->upiId);

        if ($vpa === '' || ! str_contains($vpa, '@')) {
            return null;
        }

        $value = BigDecimal::of($amount)->toScale(2, RoundingMode::HALF_UP);

        // A zero QR asks for nothing; payment apps handle that inconsistently.
        if (! $value->isPositive()) {
            return null;
        }

        $parameters = [
            'pa' => $vpa,
            'pn' => $this->sanitise($this->company->upiPayeeName ?: $this->company->name, 50),
            'am' => (string) $value,
            'cu' => 'INR',
            'tr' => $this->sanitise($reference, 35),
            'tn' => $this->sanitise($note ?? "Invoice {$reference}", 50),
        ];

        // RFC 3986 throughout: an "&" in the payee name would otherwise cut the payload short.
        return 'upi://pay?' . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    }

    public function render(string $payload, int $size = 220): string
    {
        $qr = new QrCode(
            data: $payload,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: $size,
            margin: 8,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        );

        return (new SvgWriter)->write($qr)->getDataUri();
    }

    /**
     * Strip what UPI fields cannot carry, and cap the length.
     */
    private function sanitise(string $value, int $limit): string
    {
        $clean = preg_replace('/[^A-Za-z0-9 .\-]/', '-', $value) ?? $value;

        return mb_substr(trim($clean), 0, $limit);
    }
}
