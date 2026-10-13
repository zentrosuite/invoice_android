<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('paid_on');
            $table->string('method', 20);
            // UPI reference / UTR, cheque number…
            $table->string('reference', 80)->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index('paid_on');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            // Σ payments, kept by PaymentService so lists and the dashboard need no join.
            $table->decimal('amount_paid', 14, 2)->default(0)->after('total');
        });

        // Invoices marked paid before payments were recorded get one payment for the full amount,
        // so the paid total and the payment list agree from the start.
        foreach (DB::table('invoices')->where('status', 'paid')->get(['id', 'total', 'paid_at', 'updated_at']) as $invoice) {
            DB::table('invoice_payments')->insert([
                'invoice_id' => $invoice->id,
                'amount' => $invoice->total,
                'paid_on' => substr((string) ($invoice->paid_at ?? $invoice->updated_at), 0, 10),
                'method' => 'other',
                'note' => 'Marked paid before payments were recorded',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('invoices')->where('id', $invoice->id)->update(['amount_paid' => $invoice->total]);
        }
    }

    public function down(): void
    {
        Schema::table('invoices', fn (Blueprint $table) => $table->dropColumn('amount_paid'));
        Schema::dropIfExists('invoice_payments');
    }
};
