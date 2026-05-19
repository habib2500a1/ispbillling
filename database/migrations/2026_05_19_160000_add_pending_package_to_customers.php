<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->foreignId('pending_package_id')->nullable()->after('package_id')->constrained('packages')->nullOnDelete();
            $table->date('pending_package_effective_date')->nullable()->after('pending_package_id');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('pending_package_id');
            $table->dropColumn('pending_package_effective_date');
        });
    }
};
