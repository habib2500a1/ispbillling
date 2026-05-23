<?php

use App\Support\Rbac\IspPermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resellers', function (Blueprint $table): void {
            $table->json('portal_permissions')->nullable()->after('notes');
        });

        Schema::create('reseller_settlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->default(1)->constrained()->restrictOnDelete();
            $table->foreignId('reseller_id')->constrained()->cascadeOnDelete();
            $table->string('settlement_number', 64);
            $table->decimal('amount', 12, 2);
            $table->decimal('expense_deduction', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2);
            $table->string('status', 32)->default('pending');
            $table->string('payment_method', 32)->nullable();
            $table->string('reference', 128)->nullable();
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'settlement_number']);
            $table->index(['reseller_id', 'status']);
        });

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (IspPermissionCatalog::all() as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reseller_settlements');

        Schema::table('resellers', function (Blueprint $table): void {
            $table->dropColumn('portal_permissions');
        });
    }
};
