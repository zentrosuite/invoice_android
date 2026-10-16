<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Money a customer pays before there is an invoice for it ("advance"). It sits on the
     * customer as a credit until it is adjusted against invoices.
     *
     * An adjustment is an ordinary invoice payment with method "advance", so Part paid / Paid
     * follow from it like from any payment. invoice_payments.customer_id says whose advance it
     * came from: the balance is Σ advances − Σ those payments, and stays right even if the
     * invoice is later deleted (its payments go with it, and the money is back on the customer).
     */
    public function up(): void
    {
        Schema::create('customer_advances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('received_on');
            $table->string('method', 20);
            $table->string('reference', 80)->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index('received_on');
        });

        Schema::table('invoice_payments', function (Blueprint $table): void {
            // Set only on advance adjustments. No foreign key: SQLite (the phone) cannot add one
            // to an existing table, and customers are soft-deleted anyway.
            $table->unsignedBigInteger('customer_id')->nullable()->after('invoice_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table): void {
            $table->dropIndex(['customer_id']);
            $table->dropColumn('customer_id');
        });
        Schema::dropIfExists('customer_advances');
    }
};
