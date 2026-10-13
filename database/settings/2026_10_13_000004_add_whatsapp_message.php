<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('invoice.whatsappMessage', implode("\n", [
            'Hi {customer},',
            '',
            'Here is invoice {number} from {company} for {amount}.',
            '',
            'View, download or pay: {link}',
            '',
            'Thank you!',
        ]));
    }
};
