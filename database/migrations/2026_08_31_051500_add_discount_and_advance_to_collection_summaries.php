<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('collection_summaries')) {
            return;
        }

        Schema::table('collection_summaries', function (Blueprint $table) {
            if (! Schema::hasColumn('collection_summaries', 'discount_amount')) {
                $table->decimal('discount_amount', 11, 2)->default(0)->after('collection_amount');
            }
            if (! Schema::hasColumn('collection_summaries', 'advance_amount')) {
                $table->decimal('advance_amount', 11, 2)->default(0)->after('discount_amount');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('collection_summaries')) {
            return;
        }

        Schema::table('collection_summaries', function (Blueprint $table) {
            if (Schema::hasColumn('collection_summaries', 'advance_amount')) {
                $table->dropColumn('advance_amount');
            }
            if (Schema::hasColumn('collection_summaries', 'discount_amount')) {
                $table->dropColumn('discount_amount');
            }
        });
    }
};
