<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('tenants')->insert([
            'id' => 1,
            'name' => 'Default ISP',
            'slug' => 'default',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        $tenantTables = [
            'areas',
            'zones',
            'subzones',
            'packages',
            'customers',
            'resellers',
            'invoices',
            'payments',
            'devices',
        ];

        foreach ($tenantTables as $tbl) {
            Schema::table($tbl, function (Blueprint $table) {
                $table->foreignId('tenant_id')->default(1)->after('id')->constrained()->restrictOnDelete();
            });
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['invoice_number']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->unique(['tenant_id', 'invoice_number']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['customer_code']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->unique(['tenant_id', 'customer_code']);
        });

        Schema::table('resellers', function (Blueprint $table) {
            $table->dropUnique(['code']);
        });

        Schema::table('resellers', function (Blueprint $table) {
            $table->unique(['tenant_id', 'code']);
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->dropUnique(['serial_number']);
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->unique(['tenant_id', 'serial_number']);
        });

        Schema::table('areas', function (Blueprint $table) {
            $table->dropUnique(['code']);
        });

        Schema::table('areas', function (Blueprint $table) {
            $table->unique(['tenant_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::table('areas', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'code']);
            $table->unique('code');
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'serial_number']);
            $table->unique('serial_number');
        });

        Schema::table('resellers', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'code']);
            $table->unique('code');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'customer_code']);
            $table->unique('customer_code');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'invoice_number']);
            $table->unique('invoice_number');
        });

        foreach (['devices', 'payments', 'invoices', 'customers', 'resellers', 'packages', 'subzones', 'zones', 'areas'] as $tbl) {
            Schema::table($tbl, function (Blueprint $table) {
                $table->dropConstrainedForeignId('tenant_id');
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });

        Schema::dropIfExists('tenants');
    }
};
