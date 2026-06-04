<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resellers', function (Blueprint $table): void {
            $table->decimal('bonus_wallet_balance', 14, 2)->default(0)->after('wallet_balance');
            $table->decimal('credit_limit', 14, 2)->default(0)->after('bonus_wallet_balance');
            $table->decimal('low_balance_threshold', 14, 2)->nullable()->after('credit_limit');
            $table->boolean('auto_suspend_on_low_balance')->default(false)->after('auto_suspend_enabled');
            $table->boolean('auto_restore_on_recharge')->default(true)->after('auto_suspend_on_low_balance');
            $table->string('portal_custom_domain')->nullable()->after('portal_subdomain');
            $table->string('brand_secondary_color', 16)->nullable()->after('brand_primary_color');
            $table->text('portal_login_message')->nullable()->after('brand_secondary_color');
            $table->string('hierarchy_path', 512)->nullable()->after('parent_id');
            $table->unsignedSmallInteger('hierarchy_depth')->default(0)->after('hierarchy_path');
            $table->unsignedInteger('max_onu')->nullable()->after('max_active_clients');
            $table->unsignedInteger('max_olt')->nullable()->after('max_onu');
            $table->unsignedInteger('bandwidth_quota_mbps')->nullable()->after('max_olt');
            $table->unsignedInteger('max_packages')->nullable()->after('bandwidth_quota_mbps');
            $table->string('commission_mode', 32)->default('simple')->after('commission_value');
            $table->json('allowed_ips')->nullable()->after('portal_devices');
            $table->boolean('api_access_enabled')->default(false)->after('allowed_ips');
            $table->unsignedInteger('api_rate_limit_per_minute')->default(120)->after('api_access_enabled');

            $table->index(['tenant_id', 'parent_id']);
            $table->index(['tenant_id', 'hierarchy_path']);
            $table->index(['tenant_id', 'is_active', 'franchise_type']);
        });

        Schema::create('reseller_wallet_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->default(1)->constrained()->restrictOnDelete();
            $table->foreignId('reseller_id')->constrained()->cascadeOnDelete();
            $table->string('wallet_type', 16)->default('main');
            $table->string('direction', 8);
            $table->decimal('amount', 14, 2);
            $table->decimal('balance_after', 14, 2);
            $table->string('transaction_type', 48);
            $table->string('reference', 96)->nullable();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('related_transfer_id')->nullable()->constrained('reseller_balance_transfers')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['reseller_id', 'created_at']);
            $table->index(['reseller_id', 'wallet_type', 'created_at']);
        });

        Schema::create('reseller_commission_tiers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->default(1)->constrained()->restrictOnDelete();
            $table->foreignId('reseller_id')->constrained()->cascadeOnDelete();
            $table->decimal('min_amount', 14, 2)->default(0);
            $table->decimal('max_amount', 14, 2)->nullable();
            $table->string('commission_type', 16)->default('percent');
            $table->decimal('commission_value', 10, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['reseller_id', 'sort_order']);
        });

        Schema::create('reseller_customer_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->default(1)->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_reseller_id')->constrained('resellers')->cascadeOnDelete();
            $table->foreignId('to_reseller_id')->constrained('resellers')->cascadeOnDelete();
            $table->foreignId('requested_by_reseller_id')->nullable()->constrained('resellers')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('pending');
            $table->text('reason')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['from_reseller_id', 'status']);
            $table->index(['to_reseller_id', 'status']);
            $table->index(['customer_id', 'status']);
        });

        Schema::create('reseller_api_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->default(1)->constrained()->restrictOnDelete();
            $table->foreignId('reseller_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('key_prefix', 16);
            $table->string('key_hash', 128);
            $table->json('abilities')->nullable();
            $table->unsignedInteger('rate_limit_per_minute')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['key_prefix']);
            $table->index(['reseller_id', 'is_active']);
        });

        Schema::create('reseller_api_usage_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->default(1)->constrained()->restrictOnDelete();
            $table->foreignId('reseller_api_key_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reseller_id')->constrained()->cascadeOnDelete();
            $table->string('method', 8);
            $table->string('path', 512);
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['reseller_id', 'created_at']);
            $table->index(['reseller_api_key_id', 'created_at']);
        });

        Schema::create('reseller_portal_login_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->default(1)->constrained()->restrictOnDelete();
            $table->foreignId('reseller_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reseller_staff_id')->nullable()->constrained('reseller_staff')->nullOnDelete();
            $table->string('login_id', 128)->nullable();
            $table->boolean('success')->default(false);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('device_fingerprint', 128)->nullable();
            $table->string('failure_reason', 128)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['reseller_id', 'created_at']);
        });

        Schema::create('reseller_announcements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->default(1)->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('body');
            $table->string('audience', 32)->default('all');
            $table->json('target_reseller_ids')->nullable();
            $table->string('priority', 16)->default('normal');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active', 'published_at']);
        });

        Schema::create('reseller_announcement_reads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reseller_announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reseller_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at');

            $table->unique(['reseller_announcement_id', 'reseller_id']);
        });

        Schema::create('reseller_internal_tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->default(1)->constrained()->restrictOnDelete();
            $table->foreignId('reseller_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject');
            $table->text('body');
            $table->string('category', 48)->default('general');
            $table->string('status', 32)->default('open');
            $table->string('priority', 16)->default('normal');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['reseller_id', 'status']);
        });

        Schema::create('reseller_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->default(1)->constrained()->restrictOnDelete();
            $table->foreignId('reseller_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('author_reseller_id')->nullable()->constrained('resellers')->nullOnDelete();
            $table->text('body');
            $table->boolean('is_pinned')->default(false);
            $table->timestamps();

            $table->index(['reseller_id', 'created_at']);
        });

        Schema::create('reseller_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->default(1)->constrained()->restrictOnDelete();
            $table->foreignId('reseller_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_number', 64);
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->string('status', 32)->default('open');
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->text('notes')->nullable();
            $table->json('line_items')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'invoice_number']);
            $table->index(['reseller_id', 'status']);
        });

        Schema::create('reseller_custom_roles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->default(1)->constrained()->restrictOnDelete();
            $table->foreignId('reseller_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('permissions');
            $table->json('menu_permissions')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['reseller_id', 'name']);
        });

        Schema::table('reseller_staff', function (Blueprint $table): void {
            if (! Schema::hasColumn('reseller_staff', 'reseller_custom_role_id')) {
                $table->foreignId('reseller_custom_role_id')->nullable()->after('reseller_id')
                    ->constrained('reseller_custom_roles')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('reseller_staff', 'reseller_custom_role_id')) {
            Schema::table('reseller_staff', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('reseller_custom_role_id');
            });
        }

        Schema::dropIfExists('reseller_custom_roles');
        Schema::dropIfExists('reseller_invoices');
        Schema::dropIfExists('reseller_notes');
        Schema::dropIfExists('reseller_internal_tickets');
        Schema::dropIfExists('reseller_announcement_reads');
        Schema::dropIfExists('reseller_announcements');
        Schema::dropIfExists('reseller_portal_login_logs');
        Schema::dropIfExists('reseller_api_usage_logs');
        Schema::dropIfExists('reseller_api_keys');
        Schema::dropIfExists('reseller_customer_transfers');
        Schema::dropIfExists('reseller_commission_tiers');
        Schema::dropIfExists('reseller_wallet_transactions');

        Schema::table('resellers', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'parent_id']);
            $table->dropIndex(['tenant_id', 'hierarchy_path']);
            $table->dropIndex(['tenant_id', 'is_active', 'franchise_type']);
            $table->dropColumn([
                'bonus_wallet_balance',
                'credit_limit',
                'low_balance_threshold',
                'auto_suspend_on_low_balance',
                'auto_restore_on_recharge',
                'portal_custom_domain',
                'brand_secondary_color',
                'portal_login_message',
                'hierarchy_path',
                'hierarchy_depth',
                'max_onu',
                'max_olt',
                'bandwidth_quota_mbps',
                'max_packages',
                'commission_mode',
                'allowed_ips',
                'api_access_enabled',
                'api_rate_limit_per_minute',
            ]);
        });
    }
};
