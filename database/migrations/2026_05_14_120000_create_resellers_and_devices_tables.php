<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resellers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('resellers')->nullOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('commission_type')->default('percent');
            $table->decimal('commission_value', 10, 2)->default(0);
            $table->decimal('wallet_balance', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('reseller_id')->nullable()->after('subzone_id')->constrained()->nullOnDelete();
        });

        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('onu');
            $table->string('serial_number')->unique();
            $table->string('mac_address')->nullable()->index();
            $table->string('model')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('in_stock');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');

        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reseller_id');
        });

        Schema::dropIfExists('resellers');
    }
};
