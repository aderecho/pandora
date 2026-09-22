<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('object_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stored_object_id')->constrained('stored_objects')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum_sha256', 64);
            $table->string('storage_relative_path');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_current')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['stored_object_id', 'version_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('object_versions');
    }
};
