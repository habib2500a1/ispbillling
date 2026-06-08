<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_invoices', function (Blueprint $table) {
            $table->string('payment_token', 64)->nullable()->unique()->after('invoice_number');
            $table->string('gateway', 32)->nullable()->after('payment_reference');
        });

        foreach (\App\Models\PlatformInvoice::query()->whereNull('payment_token')->get(['id']) as $row) {
            \App\Models\PlatformInvoice::query()->whereKey($row->id)->update([
                'payment_token' => Str::lower(Str::random(48)),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('platform_invoices', function (Blueprint $table) {
            $table->dropColumn(['payment_token', 'gateway']);
        });
    }
};
