<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('promotional_offer_id')
                ->nullable()
                ->after('coupon_discount_amount')
                ->constrained('promotional_offers')
                ->nullOnDelete();
            $table->decimal('promotional_offer_discount_amount', 12, 2)
                ->default(0)
                ->after('promotional_offer_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promotional_offer_id');
            $table->dropColumn('promotional_offer_discount_amount');
        });
    }
};
