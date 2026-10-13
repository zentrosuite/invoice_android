<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Enums\PricingMode;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'INV-' . fake()->unique()->numerify('####'),
            'status' => InvoiceStatus::Draft,
            'pricing_mode' => PricingMode::Itemised,
            'template' => 'classic',
            'invoice_date' => now()->startOfDay(),
            'customer_name' => fake()->name(),
            'item_label' => 'Item',
            'show_quantity' => true,
            'quantity_label' => 'Qty',
            'rate_label' => 'Rate',
            'currency_symbol' => '₹',
            'subtotal' => '1000.00',
            'total' => '1000.00',
        ];
    }

    public function status(InvoiceStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}
