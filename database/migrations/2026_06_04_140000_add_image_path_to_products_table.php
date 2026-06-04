<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'image_path')) {
                $table->string('image_path', 512)->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'image_path')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('image_path');
        });
    }
};
