<?php

declare(strict_types=1);

use App\Enums\UsageProfile;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        foreach ([
            'name' => '',
            'tagline' => null,
            'email' => null,
            'phone' => null,
            'website' => null,
            'addressLine1' => null,
            'addressLine2' => null,
            'city' => null,
            'state' => null,
            'pincode' => null,
            'taxId' => null,
            'logoPath' => null,
            'upiId' => null,
            'upiPayeeName' => null,
        ] as $key => $value) {
            $this->migrator->add("company.{$key}", $value);
        }

        // Neutral until the setup wizard records the real choice.
        $this->migrator->add('invoice.usageProfile', UsageProfile::Other->value);

        foreach (UsageProfile::Other->defaults() as $key => $value) {
            $this->migrator->add("invoice.{$key}", $value);
        }

        $this->migrator->add('invoice.numberPrefix', 'INV-');
        $this->migrator->add('invoice.nextNumber', 1);
        $this->migrator->add('invoice.defaultTaxPercent', null);
        $this->migrator->add('invoice.currencySymbol', '₹');
    }
};
