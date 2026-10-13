<?php

declare(strict_types=1);

namespace App\Settings;

use App\Enums\InvoiceTemplate;
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

    /** Digits after the prefix: 4 → INV-0001. */
    public int $numberPadding;

    /** Days from the invoice date to the due date; null means due on receipt. */
    public ?int $dueDays;

    /** Free text printed as the payment terms, e.g. "On completion". */
    public ?string $paymentTerms;

    /** One condition per line. */
    public ?string $defaultTerms;

    public ?string $defaultNotes;

    /** The closing line, e.g. "Thank you for your business." */
    public ?string $footerNote;

    public static function group(): string
    {
        return 'invoice';
    }

    /**
     * What the next invoice will be numbered, without using the number up.
     */
    public function previewNumber(): string
    {
        return $this->numberPrefix . str_pad((string) $this->nextNumber, $this->numberPadding, '0', STR_PAD_LEFT);
    }

    public function templateEnum(): InvoiceTemplate
    {
        return InvoiceTemplate::tryFrom($this->template) ?? InvoiceTemplate::Classic;
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
