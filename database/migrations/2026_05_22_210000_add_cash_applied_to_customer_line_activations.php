<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_line_activations', function (Blueprint $table): void {
            $table->decimal('cash_collected', 12, 2)->default(0)->after('wallet_applied');
        });
    }

    public function down(): void
    {
        Schema::table('customer_line_activations', function (Blueprint $table): void {
            $table->dropColumn('cash_collected');
        });
    }
};
