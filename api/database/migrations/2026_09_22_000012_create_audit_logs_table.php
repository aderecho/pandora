<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('actor_type', 20); // user|token|system
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action', 60);
            $table->foreignId('bucket_id')->nullable()->constrained('buckets')->nullOnDelete();
            $table->string('object_key')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('status', 20)->default('success');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
            $table->index(['actor_type', 'actor_id']);
            $table->index('action');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
