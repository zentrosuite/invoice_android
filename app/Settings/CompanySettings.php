<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * The business whose name goes at the top of every invoice.
 *
 * One row of settings rather than a table: there is exactly one company per installation, and
 * an Eloquent model with one row invites a second one by accident.
 */
class CompanySettings extends Settings
{
    public string $name;

    /** A one-line strap under the footer name, e.g. "Web Design • Branding • Digital Solutions". */
    public ?string $tagline;

    public ?string $email;

    public ?string $phone;

    public ?string $website;

    public ?string $addressLine1;

    public ?string $addressLine2;

    public ?string $city;

    public ?string $state;

    public ?string $pincode;

    /** GSTIN or any other registration number the owner wants printed. Free text on purpose. */
    public ?string $taxId;

    /** Relative to the public disk. */
    public ?string $logoPath;

    /** Without it no payment QR is drawn — there is nobody to pay. */
    public ?string $upiId;

    /** The name UPI apps show the payer before they confirm. Falls back to $name. */
    public ?string $upiPayeeName;

    public static function group(): string
    {
        return 'company';
    }

    public function isConfigured(): bool
    {
        return trim($this->name) !== '';
    }

    /**
     * Non-empty address lines, top to bottom, ready to print.
     *
     * @return list<string>
     */
    public function addressLines(): array
    {
        $cityLine = trim(implode(' ', array_filter([
            trim(implode(', ', array_filter([$this->city, $this->state]))),
            $this->pincode ? '- ' . $this->pincode : null,
        ])));

        return array_values(array_filter(
            [$this->addressLine1, $this->addressLine2, $cityLine],
            static fn (?string $line): bool => $line !== null && trim($line) !== '',
        ));
    }
}
