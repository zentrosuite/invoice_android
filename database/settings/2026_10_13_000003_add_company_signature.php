<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Relative to public/. Without one, invoices print no "Authorised signatory" block.
        $this->migrator->add('company.signaturePath', null);
    }
};
