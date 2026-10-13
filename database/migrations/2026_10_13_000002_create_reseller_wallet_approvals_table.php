<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = 'reseller_wallet_approvals';

        // The production deploy may have created the table before MySQL rejected
        // its generated composite index name. Keep retries safe for that case.
        if (! Schema::hasTable($tableName)) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->foreignId('saas_operator_id')->nullable()->constrained('saas_operators')->nullOnDelete();
                $table->foreignId('reseller_id')->constrained('resellers')->cascadeOnDelete();
                $table->decimal('amount', 15, 2);
                $table->string('payment_method', 60);
                $table->string('payment_reference')->nullable();
                $table->text('note')->nullable();
                $table->string('status', 20)->default('pending')->index();
                $table->text('admin_note')->nullable();
                $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('wallet_transaction_id')->nullable()->constrained('reseller_wallet_transactions')->nullOnDelete();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();
            });
        }

        $hasIndexForColumns = static function (array $columns) use ($tableName): bool {
            return collect(Schema::getIndexes($tableName))->contains(
                fn (array $index) => array_values($index['columns'] ?? []) === $columns
            );
        };

        if (! $hasIndexForColumns(['status'])) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->index('status', 'rwa_status_idx');
            });
        }

        if (! $hasIndexForColumns(['reseller_id', 'status', 'created_at'])) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->index(['reseller_id', 'status', 'created_at'], 'rwa_reseller_status_created_idx');
            });
        }

        if (! $hasIndexForColumns(['saas_operator_id', 'status', 'created_at'])) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->index(['saas_operator_id', 'status', 'created_at'], 'rwa_tenant_status_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reseller_wallet_approvals');
    }
};
