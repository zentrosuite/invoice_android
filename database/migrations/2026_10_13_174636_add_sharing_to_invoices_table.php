<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            // The secret in the customer's link (/i/{token}). Unguessable, resettable; null until
            // the invoice is first opened for sharing.
            $table->string('share_token', 48)->nullable()->unique()->after('footer_note');
            $table->timestamp('shared_at')->nullable()->after('paid_at');
            // When the customer last opened their link — useful to know it was seen.
            $table->timestamp('viewed_at')->nullable()->after('shared_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn(['share_token', 'shared_at', 'viewed_at']);
        });
    }
};
