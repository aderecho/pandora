<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stored_objects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bucket_id')->constrained('buckets')->cascadeOnDelete();
            $table->string('key');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('mime_type')->nullable();
            $table->string('checksum_sha256', 64)->nullable();
            $table->string('etag')->nullable();
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('uploaded_via_token_id')->nullable()->constrained('api_tokens')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['bucket_id', 'key']);
        });

        // Only one *live* object may occupy a given key within a bucket at a
        // time; a soft-deleted key can be re-uploaded (mirrors S3 semantics).
        DB::statement(
            'CREATE UNIQUE INDEX stored_objects_bucket_key_live_unique ON stored_objects (bucket_id, key) WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('stored_objects');
    }
};
