<?php

declare(strict_types=1);

namespace App\Settings;

use Illuminate\Support\Carbon;
use Spatie\LaravelSettings\Settings;

/**
 * When the owner last kept a backup somewhere off the phone.
 */
class BackupSettings extends Settings
{
    /** ISO-8601, or null when no backup has ever been saved. */
    public ?string $lastSavedAt;

    public static function group(): string
    {
        return 'backup';
    }

    public function lastSaved(): ?Carbon
    {
        return $this->lastSavedAt ? Carbon::parse($this->lastSavedAt) : null;
    }

    /**
     * Worth a reminder: there are invoices, and none have been backed up in a week.
     */
    public function isOverdue(bool $hasData): bool
    {
        return $hasData && ($this->lastSaved() === null || $this->lastSaved()->lt(now()->subDays(7)));
    }
}
