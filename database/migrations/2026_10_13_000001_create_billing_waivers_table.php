<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_waivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saas_operator_id')->nullable()->constrained('saas_operators')->nullOnDelete();
            $table->string('customer_unique_id')->index();
            $table->decimal('amount', 15, 2);
            $table->text('reason');
            $table->foreignId('waived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('customer_unique_id')
                ->references('customer_unique_id')->on('customers_infos')
                ->cascadeOnUpdate()->cascadeOnDelete();
            $table->index(['saas_operator_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_waivers');
    }
};
