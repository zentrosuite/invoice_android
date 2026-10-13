<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('invoice.whatsappApp', 'business');

        // On the phone the PDF itself is sent, so the message is its caption — no link line.
        $this->migrator->update('invoice.whatsappMessage', fn (string $message): string => implode("\n", [
            'Hi {customer},',
            '',
            'Please find invoice {number} from {company} for {amount}.',
            '',
            'Thank you!',
        ]));
    }
};
