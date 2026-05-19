<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->default(1)->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('label', 32)->default('mobile');
            $table->string('phone', 32);
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_whatsapp')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'phone']);
        });

        Schema::create('customer_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->default(1)->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 32);
            $table->string('disk', 32)->default('local');
            $table->string('path');
            $table->string('original_filename')->nullable();
            $table->string('mime_type', 128)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'document_type']);
        });

        Schema::create('customer_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->default(1)->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category', 32)->default('general');
            $table->text('body');
            $table->json('meta')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->timestamps();

            $table->index(['customer_id', 'created_at']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('nid_front_path')->nullable()->after('nid_number');
            $table->string('nid_back_path')->nullable()->after('nid_front_path');
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->string('connection_type', 32)->nullable()->after('type');
        });

        if (Schema::hasTable('customers')) {
            DB::table('customers')->where('status', 'inactive')->update(['status' => 'expired']);
        }
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn('connection_type');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['nid_front_path', 'nid_back_path']);
        });

        Schema::dropIfExists('customer_notes');
        Schema::dropIfExists('customer_documents');
        Schema::dropIfExists('customer_contacts');
    }
};
