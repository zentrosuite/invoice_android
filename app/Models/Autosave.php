<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Unsaved invoice-builder work. See the autosaves migration and AutosaveStore.
 *
 * @property string $key
 * @property string $payload
 * @property ?string $label
 */
class Autosave extends Model
{
    public const NEW_INVOICE = 'invoice:new';

    /** @var list<string> */
    protected $guarded = ['id'];

    public static function forInvoice(Invoice $invoice): string
    {
        return 'invoice:' . $invoice->id;
    }

    /** Whether $key names a builder ("invoice:new" or "invoice:{id}"). */
    public static function isValidKey(string $key): bool
    {
        return preg_match('/^invoice:(new|[1-9][0-9]{0,18})$/', $key) === 1;
    }

    /** The invoice being edited, or null for a new invoice. */
    public function invoiceId(): ?int
    {
        return $this->key === self::NEW_INVOICE ? null : (int) substr($this->key, strlen('invoice:'));
    }

    /**
     * The form fields, as Laravel would have received them on submit.
     *
     * @return array<string, mixed>
     */
    public function input(): array
    {
        parse_str($this->payload, $input);
        unset($input['_token'], $input['_method']);

        return $input;
    }
}
