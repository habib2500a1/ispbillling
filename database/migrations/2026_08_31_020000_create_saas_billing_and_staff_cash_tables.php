<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('monthly_price')->default(0);
            $table->unsignedInteger('yearly_price')->default(0);
            $table->unsignedInteger('per_user_rate')->default(0);
            $table->unsignedInteger('max_customers')->default(0);
            $table->unsignedInteger('max_olts')->default(0);
            $table->unsignedInteger('max_onus')->default(0);
            $table->unsignedInteger('max_routers')->default(0);
            $table->unsignedInteger('max_staff')->default(0);
            $table->json('modules')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_lifetime')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(10);
            $table->timestamps();
        });

        Schema::table('saas_operators', function (Blueprint $table) {
            $table->foreignId('saas_plan_id')->nullable()->after('user_id')->constrained('saas_plans')->nullOnDelete();
            $table->string('billing_cycle')->default('monthly')->after('plan');
            $table->unsignedInteger('base_amount')->default(0)->after('billing_cycle');
            $table->unsignedInteger('per_user_rate')->default(0)->after('base_amount');
            $table->unsignedInteger('user_base_count')->default(0)->after('per_user_rate');
            $table->unsignedInteger('amount')->default(0)->after('user_base_count');
            $table->timestamp('next_due_at')->nullable()->after('sold_at');
            $table->timestamp('last_paid_at')->nullable()->after('next_due_at');
            $table->timestamp('locked_at')->nullable()->after('last_paid_at');
            $table->string('lock_reason')->nullable()->after('locked_at');
            $table->unsignedInteger('max_customers')->default(0)->after('lock_reason');
            $table->unsignedInteger('max_olts')->default(0)->after('max_customers');
            $table->unsignedInteger('max_onus')->default(0)->after('max_olts');
            $table->unsignedInteger('max_routers')->default(0)->after('max_onus');
            $table->unsignedInteger('max_staff')->default(0)->after('max_routers');
            $table->json('modules')->nullable()->after('max_staff');
        });

        Schema::create('saas_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saas_operator_id')->constrained('saas_operators')->cascadeOnDelete();
            $table->string('period_label')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->unsignedInteger('user_base')->default(0);
            $table->unsignedInteger('amount')->default(0);
            $table->string('status')->default('due');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('paid_note')->nullable();
            $table->timestamps();
        });

        Schema::create('staff_cash_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('saas_operator_id')->nullable()->constrained('saas_operators')->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->default('deposit');
            $table->unsignedInteger('amount')->default(0);
            $table->date('entry_date');
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('saas_operator_id')->nullable()->after('id')->index();
        });

        if (Schema::hasTable('olts') && ! Schema::hasColumn('olts', 'saas_operator_id')) {
            Schema::table('olts', function (Blueprint $table) {
                $table->unsignedBigInteger('saas_operator_id')->nullable()->after('id')->index();
            });
        }

        if (Schema::hasTable('router_lists') && ! Schema::hasColumn('router_lists', 'saas_operator_id')) {
            Schema::table('router_lists', function (Blueprint $table) {
                $table->unsignedBigInteger('saas_operator_id')->nullable()->after('id')->index();
            });
        }

        if (Schema::hasTable('customers_infos') && ! Schema::hasColumn('customers_infos', 'saas_operator_id')) {
            Schema::table('customers_infos', function (Blueprint $table) {
                $table->unsignedBigInteger('saas_operator_id')->nullable()->after('id')->index();
            });
        }
    }

    public function down(): void
    {
        foreach (['customers_infos', 'router_lists', 'olts', 'users'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'saas_operator_id')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropColumn('saas_operator_id');
                });
            }
        }

        Schema::dropIfExists('staff_cash_entries');
        Schema::dropIfExists('saas_invoices');

        if (Schema::hasTable('saas_operators')) {
            Schema::table('saas_operators', function (Blueprint $table) {
                foreach ([
                    'modules', 'max_staff', 'max_routers', 'max_onus', 'max_olts', 'max_customers',
                    'lock_reason', 'locked_at', 'last_paid_at', 'next_due_at', 'amount',
                    'user_base_count', 'per_user_rate', 'base_amount', 'billing_cycle',
                ] as $column) {
                    if (Schema::hasColumn('saas_operators', $column)) {
                        $table->dropColumn($column);
                    }
                }
                if (Schema::hasColumn('saas_operators', 'saas_plan_id')) {
                    $table->dropConstrainedForeignId('saas_plan_id');
                }
            });
        }

        Schema::dropIfExists('saas_plans');
    }
};
