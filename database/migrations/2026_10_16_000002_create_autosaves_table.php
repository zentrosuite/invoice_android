<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The invoice builder's unsaved work, written every few seconds while typing, so an app that
     * was closed, killed or crashed half way through an invoice can carry on where it stopped.
     * One row per builder: "invoice:new", or "invoice:{id}" while editing. See App\Models\Autosave.
     */
    public function up(): void
    {
        Schema::create('autosaves', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 40)->unique();
            // The form exactly as it would be submitted (URL-encoded), restored through old().
            $table->longText('payload');
            // "Zentro Builders · 3 lines · ₹2,400", for the "continue" prompt.
            $table->string('label', 200)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('autosaves');
    }
};
