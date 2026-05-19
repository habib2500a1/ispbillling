<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resellers', function (Blueprint $table) {
            $table->string('franchise_type', 32)->default('reseller')->after('parent_id');
            $table->decimal('revenue_share_percent', 5, 2)->default(0)->after('commission_value');
            $table->boolean('white_label_enabled')->default(false)->after('revenue_share_percent');
            $table->string('brand_name')->nullable()->after('white_label_enabled');
            $table->string('brand_logo_path')->nullable()->after('brand_name');
            $table->string('brand_primary_color', 16)->nullable()->after('brand_logo_path');
            $table->string('portal_subdomain')->nullable()->after('brand_primary_color');
            $table->string('contact_person')->nullable()->after('email');
        });

        Schema::create('reseller_territories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->default(1)->constrained()->restrictOnDelete();
            $table->foreignId('reseller_id')->constrained()->cascadeOnDelete();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subzone_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['reseller_id', 'area_id']);
        });

        Schema::create('reseller_balance_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->default(1)->constrained()->restrictOnDelete();
            $table->foreignId('from_reseller_id')->nullable()->constrained('resellers')->nullOnDelete();
            $table->foreignId('to_reseller_id')->constrained('resellers')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('transfer_type', 32)->default('transfer');
            $table->string('reference', 64)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['to_reseller_id', 'created_at']);
        });

        Schema::create('reseller_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->default(1)->constrained()->restrictOnDelete();
            $table->foreignId('reseller_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('gross_amount', 12, 2);
            $table->decimal('commission_amount', 12, 2)->default(0);
            $table->decimal('parent_share_amount', 12, 2)->default(0);
            $table->string('status', 32)->default('pending');
            $table->timestamp('earned_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['reseller_id', 'status']);
            $table->index(['payment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reseller_commissions');
        Schema::dropIfExists('reseller_balance_transfers');
        Schema::dropIfExists('reseller_territories');

        Schema::table('resellers', function (Blueprint $table) {
            $table->dropColumn([
                'franchise_type',
                'revenue_share_percent',
                'white_label_enabled',
                'brand_name',
                'brand_logo_path',
                'brand_primary_color',
                'portal_subdomain',
                'contact_person',
            ]);
        });
    }
};
