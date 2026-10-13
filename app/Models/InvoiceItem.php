<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Inclusion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $invoice_id
 * @property int $position
 * @property string $description
 * @property string|null $details
 * @property string|null $quantity
 * @property string|null $rate
 * @property string $amount
 * @property Inclusion|null $inclusion
 */
class InvoiceItem extends Model
{
    /** @var list<string> */
    protected $guarded = ['id'];

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Sub-points, for the bullets under the description.
     *
     * @return list<string>
     */
    public function detailLines(): array
    {
        return Invoice::lines($this->details);
    }

    /**
     * Quantity without trailing zeros: 2.000 → "2", 1.500 → "1.5".
     */
    public function quantityForDisplay(): string
    {
        if ($this->quantity === null) {
            return '';
        }

        return rtrim(rtrim((string) $this->quantity, '0'), '.');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'rate' => 'decimal:2',
            'amount' => 'decimal:2',
            'inclusion' => Inclusion::class,
        ];
    }
}
