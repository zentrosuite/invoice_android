<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            // The person; the business is optional (a walk-in customer has none).
            $table->string('name', 150);
            $table->string('business_name', 150)->nullable();
            // As typed, for display.
            $table->string('phone', 30)->nullable();
            // Digits only, last ten for Indian numbers — what search and duplicate checks compare.
            $table->string('phone_digits', 20)->nullable()->index();
            $table->string('email', 150)->nullable();
            $table->string('address_line1', 150)->nullable();
            $table->string('address_line2', 150)->nullable();
            $table->string('city', 80)->nullable();
            $table->string('state', 80)->nullable();
            $table->string('pincode', 12)->nullable();
            $table->string('tax_id', 40)->nullable();
            // Private to the owner; never printed.
            $table->text('notes')->nullable();
            $table->timestamps();
            // Soft: a customer's past invoices must keep their name after the customer is removed.
            $table->softDeletes();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
