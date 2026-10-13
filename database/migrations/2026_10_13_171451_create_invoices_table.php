<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 30)->unique();
            $table->string('status', 20)->default('draft')->index();

            // 'itemised' (quantity × rate per line) or 'package' (lines Included / Not included,
            // one total). See App\Enums\PricingMode.
            $table->string('pricing_mode', 20);
            $table->string('template', 20);

            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->string('payment_terms', 80)->nullable();

            // Null for a walk-in sale. The customer_* columns are a COPY taken when the invoice is
            // saved: an invoice already sent must not change because the customer moved house.
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name', 150)->nullable();
            $table->string('customer_business', 150)->nullable();
            $table->string('customer_phone', 30)->nullable();
            $table->string('customer_email', 150)->nullable();
            $table->text('customer_address')->nullable();
            $table->string('customer_tax_id', 40)->nullable();

            // Column headings as they were when the invoice was made, copied from settings.
            $table->string('item_label', 40);
            $table->boolean('show_quantity');
            $table->string('quantity_label', 20);
            $table->string('rate_label', 20);
            $table->string('currency_symbol', 5);

            // Money. Every figure below is recomputed on the server from the lines on each save.
            // package_total is the one INPUT among them: the price of a package invoice.
            $table->decimal('package_total', 14, 2)->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->string('discount_type', 10)->nullable(); // 'amount' | 'percent'
            $table->decimal('discount_value', 14, 2)->nullable();
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('tax_percent', 5, 2)->nullable();
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);

            // Optional highlighted box, e.g. "Annual Domain Renewal — ₹899 / year, renews on …".
            $table->json('callout')->nullable();

            $table->text('terms')->nullable();
            $table->text('notes')->nullable();
            $table->string('footer_note', 120)->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index('invoice_date');
        });

        Schema::create('invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('description', 255);
            // Sub-points, one per line, printed as bullets under the description.
            $table->text('details')->nullable();

            // Itemised lines.
            $table->decimal('quantity', 12, 3)->nullable();
            $table->decimal('rate', 14, 2)->nullable();
            $table->decimal('amount', 14, 2)->default(0);

            // Package lines: 'included' | 'excluded'.
            $table->string('inclusion', 10)->nullable();

            $table->timestamps();

            $table->index(['invoice_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
