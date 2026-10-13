<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Digits after the prefix: 4 gives INV-0001.
        $this->migrator->add('invoice.numberPadding', 4);

        // Days until due; null means "due on receipt" and no due date is printed.
        $this->migrator->add('invoice.dueDays', null);

        // Printed beside the date, e.g. "On completion", "Within 7 days".
        $this->migrator->add('invoice.paymentTerms', null);

        // One condition per line; printed as a bulleted list.
        $this->migrator->add('invoice.defaultTerms', null);

        $this->migrator->add('invoice.defaultNotes', null);

        $this->migrator->add('invoice.footerNote', 'Thank you for your business.');
    }
};
