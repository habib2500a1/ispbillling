<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_drives', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('mount_path');
            $table->boolean('enabled')->default(true);
            $table->boolean('mirror_on_backup')->default(true);
            $table->unsignedSmallInteger('max_archives')->nullable();
            $table->unsignedSmallInteger('retention_days')->nullable();
            $table->timestamp('last_mirrored_at')->nullable();
            $table->string('last_mirror_status', 32)->nullable();
            $table->text('last_mirror_error')->nullable();
            $table->unsignedBigInteger('last_mirror_size_bytes')->nullable();
            $table->timestamps();

            $table->unique('mount_path');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_drives');
    }
};
