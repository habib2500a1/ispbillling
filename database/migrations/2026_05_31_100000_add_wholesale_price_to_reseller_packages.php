<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reseller_packages', function (Blueprint $table): void {
            $table->decimal('wholesale_price', 12, 2)->nullable()->after('selling_price');
        });

        DB::table('reseller_packages')
            ->whereNull('wholesale_price')
            ->where('selling_price', '>', 0)
            ->update(['wholesale_price' => DB::raw('selling_price')]);
    }

    public function down(): void
    {
        Schema::table('reseller_packages', function (Blueprint $table): void {
            $table->dropColumn('wholesale_price');
        });
    }
};
