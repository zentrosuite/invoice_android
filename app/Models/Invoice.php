<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceTemplate;
use App\Enums\PricingMode;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $number
 * @property InvoiceStatus $status
 * @property PricingMode $pricing_mode
 * @property string $template
 * @property Carbon $invoice_date
 * @property Carbon|null $due_date
 * @property string|null $payment_terms
 * @property int|null $customer_id
 * @property string|null $customer_name
 * @property string|null $customer_business
 * @property string|null $customer_phone
 * @property string|null $customer_email
 * @property string|null $customer_address
 * @property string|null $customer_tax_id
 * @property string $item_label
 * @property bool $show_quantity
 * @property string $quantity_label
 * @property string $rate_label
 * @property string $currency_symbol
 * @property string|null $package_total
 * @property string $subtotal
 * @property string|null $discount_type
 * @property string|null $discount_value
 * @property string $discount_amount
 * @property string|null $tax_percent
 * @property string $tax_amount
 * @property string $total
 * @property array{title?: string, text?: string, amount?: string, suffix?: string, meta?: string}|null $callout
 * @property string|null $terms
 * @property string|null $notes
 * @property string|null $footer_note
 * @property Carbon|null $sent_at
 * @property Carbon|null $paid_at
 * @property string|null $share_token
 * @property Carbon|null $shared_at
 * @property Carbon|null $viewed_at
 */
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    /**
     * Everything is set explicitly by InvoiceService; nothing is mass-assigned from a request.
     *
     * @var list<string>
     */
    protected $guarded = ['id'];

    /**
     * Never in JSON: the token is the whole of the link's protection.
     *
     * @var list<string>
     */
    protected $hidden = ['share_token'];

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        // withTrashed: a removed customer still owns their old invoices.
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /**
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('position');
    }

    public function templateEnum(): InvoiceTemplate
    {
        return InvoiceTemplate::tryFrom($this->template) ?? InvoiceTemplate::Classic;
    }

    public function isPackage(): bool
    {
        return $this->pricing_mode === PricingMode::Package;
    }

    /**
     * Who it is billed to, for lists: the business when there is one, else the person.
     */
    public function billedTo(): string
    {
        return $this->customer_business ?: ($this->customer_name ?: 'Walk-in customer');
    }

    /**
     * Customer phone in wa.me form, from the copy on the invoice.
     */
    public function whatsappNumber(): ?string
    {
        return (new Customer(['phone' => $this->customer_phone]))->whatsappNumber();
    }

    /**
     * @return list<string>
     */
    public function termsList(): array
    {
        return self::lines($this->terms);
    }

    public function hasCallout(): bool
    {
        return is_array($this->callout) && trim((string) ($this->callout['title'] ?? '')) !== '';
    }

    /**
     * Number, customer name/business or phone.
     *
     * @param  Builder<Invoice>  $query
     * @return Builder<Invoice>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%' . addcslashes($term, '%_\\') . '%';

        return $query->where(function (Builder $q) use ($like): void {
            $q->where('number', 'like', $like)
                ->orWhere('customer_name', 'like', $like)
                ->orWhere('customer_business', 'like', $like)
                ->orWhere('customer_phone', 'like', $like);
        });
    }

    /**
     * Non-blank trimmed lines of a text block.
     *
     * @return list<string>
     */
    public static function lines(?string $text): array
    {
        return array_values(array_filter(
            array_map('trim', preg_split('/\R/', (string) $text) ?: []),
            static fn (string $line): bool => $line !== '',
        ));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'pricing_mode' => PricingMode::class,
            'invoice_date' => 'date',
            'due_date' => 'date',
            'show_quantity' => 'boolean',
            'package_total' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_percent' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'callout' => 'array',
            'sent_at' => 'datetime',
            'paid_at' => 'datetime',
            'shared_at' => 'datetime',
            'viewed_at' => 'datetime',
        ];
    }
}
