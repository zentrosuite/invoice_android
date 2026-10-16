<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use Brick\Math\BigDecimal;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Someone invoices are made out to. Saved once, picked from a list after that.
 *
 * @property int $id
 * @property string $name
 * @property string|null $business_name
 * @property string|null $phone
 * @property string|null $phone_digits
 * @property string|null $email
 * @property string|null $address_line1
 * @property string|null $address_line2
 * @property string|null $city
 * @property string|null $state
 * @property string|null $pincode
 * @property string|null $tax_id
 * @property string|null $notes
 */
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'business_name',
        'phone',
        'email',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'pincode',
        'tax_id',
        'notes',
    ];

    /**
     * @return HasMany<Invoice, $this>
     */
    /**
     * @return HasMany<CustomerAdvance, $this>
     */
    public function advances(): HasMany
    {
        return $this->hasMany(CustomerAdvance::class);
    }

    /**
     * Advance payments adjusted against invoices, newest first.
     *
     * @return HasMany<InvoicePayment, $this>
     */
    public function advanceAdjustments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class)->where('method', PaymentMethod::Advance->value);
    }

    /**
     * Advance received and not yet adjusted against an invoice: Σ advances − Σ adjustments.
     */
    public function advanceBalance(): string
    {
        $received = (string) ($this->advances()->sum('amount') ?: '0');
        $used = (string) ($this->advanceAdjustments()->sum('amount') ?: '0');
        $balance = BigDecimal::of($received)->minus($used)->toScale(2);

        return (string) ($balance->isNegative() ? BigDecimal::zero()->toScale(2) : $balance);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * The comparable form of a phone number.
     *
     * Indian numbers are written a dozen ways — "+91 98765 43210", "098765-43210", "9876543210" —
     * and all of them are the same customer. The last ten digits are what identifies an Indian
     * mobile; anything shorter (a landline, a foreign number) is kept whole.
     */
    public static function normalisePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) > 10 && (str_starts_with($digits, '91') || str_starts_with($digits, '0'))) {
            $digits = substr($digits, -10);
        }

        return $digits;
    }

    /**
     * The number in the international form wa.me expects, or null.
     *
     * Ten digits are assumed to be an Indian mobile and get the 91 country code. Anything longer
     * was typed with its own country code and is used as is.
     */
    public function whatsappNumber(): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $this->phone) ?? '';

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        if (strlen($digits) === 10) {
            return '91' . $digits;
        }

        return strlen($digits) >= 11 ? $digits : null;
    }

    /**
     * The name to print: "Rasi (5 Star Sales and Service)" style decisions live in the views;
     * this is the one-line label for lists and pickers.
     */
    public function label(): string
    {
        return $this->business_name ? "{$this->name} — {$this->business_name}" : $this->name;
    }

    /**
     * @return list<string>
     */
    public function addressLines(): array
    {
        $cityLine = trim(implode(' ', array_filter([
            trim(implode(', ', array_filter([$this->city, $this->state]))),
            $this->pincode ? '- ' . $this->pincode : null,
        ])));

        return array_values(array_filter(
            [$this->address_line1, $this->address_line2, $cityLine],
            static fn (?string $line): bool => $line !== null && trim($line) !== '',
        ));
    }

    /**
     * Name, business, phone or email containing the term. Phone matching ignores formatting.
     *
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%' . addcslashes($term, '%_\\') . '%';
        $digits = preg_replace('/\D+/', '', $term) ?? '';

        return $query->where(function (Builder $q) use ($like, $digits): void {
            $q->where('name', 'like', $like)
                ->orWhere('business_name', 'like', $like)
                ->orWhere('email', 'like', $like);

            // Three digits before phone matching kicks in, or "2" would match half the list.
            if (strlen($digits) >= 3) {
                $q->orWhere('phone_digits', 'like', '%' . $digits . '%');
            }
        });
    }

    protected static function booted(): void
    {
        static::saving(function (Customer $customer): void {
            $customer->phone_digits = self::normalisePhone($customer->phone);
        });
    }
}
