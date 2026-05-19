<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_links')) {
            return;
        }

        if (Schema::hasColumn('payment_links', 'source_event')) {
            return;
        }

        Schema::table('payment_links', function (Blueprint $table): void {
            $table->string('source_event', 64)->nullable()->after('purpose');
            $table->timestamp('first_clicked_at')->nullable()->after('access_count');
            $table->foreignId('converted_payment_id')->nullable()->after('used_at')->constrained('payments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payment_links', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('converted_payment_id');
            $table->dropColumn(['source_event', 'first_clicked_at']);
        });
    }
};
