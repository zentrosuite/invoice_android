<?php

declare(strict_types=1);

namespace App\Settings;

use App\Enums\UsageProfile;
use Spatie\LaravelSettings\Settings;

/**
 * How a new invoice starts out: its columns, wording, design and number.
 *
 * Seeded from the UsageProfile chosen during setup; each value is still individually editable
 * afterwards, so "Other" users — and anyone whose business does not fit a preset — can shape
 * the grid themselves.
 */
class InvoiceSettings extends Settings
{
    public string $usageProfile;

    /** Heading of the description column: "Item", "Service description", … */
    public string $itemLabel;

    /** Off for flat-amount billing: each line is then just a description and an amount. */
    public bool $showQuantity;

    public string $quantityLabel;

    public string $rateLabel;

    /** Key of the invoice design, see resources/views/invoices/templates. */
    public string $template;

    public string $numberPrefix;

    public int $nextNumber;

    /** Applied to new invoices when set; null means "no tax line". A simple %, not GST splits. */
    public ?float $defaultTaxPercent;

    public string $currencySymbol;

    public static function group(): string
    {
        return 'invoice';
    }

    public function profile(): UsageProfile
    {
        return UsageProfile::tryFrom($this->usageProfile) ?? UsageProfile::Other;
    }

    /**
     * Switch profile and reset the grid to that profile's defaults. Does not save.
     */
    public function applyProfile(UsageProfile $profile): void
    {
        $this->usageProfile = $profile->value;

        foreach ($profile->defaults() as $property => $value) {
            $this->{$property} = $value;
        }
    }
}
