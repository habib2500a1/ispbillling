<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->default(1)->constrained()->restrictOnDelete();
            $table->string('code');
            $table->string('discount_type', 32);
            $table->decimal('value', 12, 2);
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('uses_count')->default(0);
            $table->decimal('min_invoice_amount', 12, 2)->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->string('pricing_model', 24)->default('speed')->after('type');
            $table->decimal('included_data_gb', 12, 2)->nullable()->after('upload_mbps');
            $table->unsignedInteger('time_quota_hours')->nullable()->after('included_data_gb');
            $table->string('billing_cycle_type', 24)->default('monthly')->after('billing_cycle_days');
            $table->date('promo_starts_at')->nullable()->after('is_active');
            $table->date('promo_ends_at')->nullable()->after('promo_starts_at');
            $table->decimal('promo_price_monthly', 12, 2)->nullable()->after('promo_ends_at');
            $table->json('slab_pricing')->nullable()->after('promo_price_monthly');
            $table->decimal('sd_percent', 5, 2)->default(0)->after('vat_percent');
            $table->decimal('withholding_percent', 5, 2)->default(0)->after('sd_percent');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('billing_mode', 16)->default('postpaid')->after('billing_day');
            $table->unsignedSmallInteger('grace_period_days')->default(10)->after('billing_mode');
            $table->decimal('late_fee_fixed', 12, 2)->default(0)->after('grace_period_days');
            $table->decimal('late_fee_percent', 5, 2)->default(0)->after('late_fee_fixed');
            $table->string('late_fee_period', 16)->default('daily')->after('late_fee_percent');
            $table->decimal('reconnection_fee_amount', 12, 2)->default(0)->after('late_fee_period');
            $table->decimal('security_deposit_required', 12, 2)->default(0)->after('reconnection_fee_amount');
            $table->decimal('security_deposit_collected', 12, 2)->default(0)->after('security_deposit_required');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('sd_amount', 12, 2)->default(0)->after('tax_amount');
            $table->decimal('withholding_amount', 12, 2)->default(0)->after('sd_amount');
            $table->foreignId('coupon_id')->nullable()->after('withholding_amount')->constrained('coupons')->nullOnDelete();
            $table->decimal('coupon_discount_amount', 12, 2)->default(0)->after('coupon_id');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->string('item_type', 48)->default('line')->after('invoice_id');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('line_total');
            $table->json('meta')->nullable()->after('sort_order');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('proof_path')->nullable()->after('reference');
            $table->string('gateway', 64)->nullable()->after('proof_path');
            $table->string('gateway_transaction_id')->nullable()->after('gateway');
        });

        Schema::create('package_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->default(1)->constrained()->restrictOnDelete();
            $table->foreignId('package_id')->constrained('packages')->cascadeOnDelete();
            $table->string('addon_type', 48);
            $table->string('label');
            $table->decimal('price_monthly', 12, 2)->default(0);
            $table->json('config')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('package_area_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->default(1)->constrained()->restrictOnDelete();
            $table->foreignId('package_id')->constrained('packages')->cascadeOnDelete();
            $table->foreignId('area_id')->constrained('areas')->cascadeOnDelete();
            $table->decimal('price_monthly', 12, 2);
            $table->timestamps();

            $table->unique(['package_id', 'area_id']);
        });

        Schema::create('package_zone_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->default(1)->constrained()->restrictOnDelete();
            $table->foreignId('package_id')->constrained('packages')->cascadeOnDelete();
            $table->foreignId('zone_id')->constrained('zones')->cascadeOnDelete();
            $table->decimal('price_monthly', 12, 2);
            $table->timestamps();

            $table->unique(['package_id', 'zone_id']);
        });

        Schema::create('billing_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->morphs('auditable');
            $table->string('event', 64);
            $table->json('properties')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_audit_logs');
        Schema::dropIfExists('package_zone_prices');
        Schema::dropIfExists('package_area_prices');
        Schema::dropIfExists('package_addons');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['proof_path', 'gateway', 'gateway_transaction_id']);
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn(['item_type', 'sort_order', 'meta']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn(['sd_amount', 'withholding_amount', 'coupon_discount_amount']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'billing_mode',
                'grace_period_days',
                'late_fee_fixed',
                'late_fee_percent',
                'late_fee_period',
                'reconnection_fee_amount',
                'security_deposit_required',
                'security_deposit_collected',
            ]);
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn([
                'pricing_model',
                'included_data_gb',
                'time_quota_hours',
                'billing_cycle_type',
                'promo_starts_at',
                'promo_ends_at',
                'promo_price_monthly',
                'slab_pricing',
                'sd_percent',
                'withholding_percent',
            ]);
        });

        Schema::dropIfExists('coupons');
    }
};
