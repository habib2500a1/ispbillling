<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_portal_mirror_runs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('run_uuid', 64)->unique();
            $table->string('mode', 32)->default('mirror')->index();
            $table->string('base_url', 255);
            $table->string('status', 32)->default('running')->index();
            $table->json('options')->nullable();
            $table->json('summary')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('legacy_portal_mirror_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('legacy_portal_mirror_run_id')
                ->constrained('legacy_portal_mirror_runs')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('domain', 64)->index();
            $table->string('source_key', 255)->nullable()->index();
            $table->string('method', 8)->default('GET');
            $table->string('url', 1000);
            $table->json('request')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('content_type', 120)->nullable();
            $table->string('checksum', 64)->index();
            $table->json('payload_json')->nullable();
            $table->longText('payload_text')->nullable();
            $table->timestamp('fetched_at')->index();
            $table->timestamps();

            $table->unique(
                ['legacy_portal_mirror_run_id', 'domain', 'source_key', 'checksum'],
                'legacy_mirror_run_domain_source_checksum_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_portal_mirror_records');
        Schema::dropIfExists('legacy_portal_mirror_runs');
    }
};
