<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buckets', function (Blueprint $table) {
            $table->id();
            $table->string('name', 63)->unique();
            $table->string('slug', 63)->unique();
            $table->string('storage_path');
            $table->string('driver', 20)->default('local');
            $table->unsignedBigInteger('quota_bytes')->nullable();
            $table->unsignedBigInteger('max_file_size_bytes')->nullable();
            $table->json('allowed_extensions')->nullable();
            $table->json('allowed_mime_types')->nullable();
            $table->boolean('versioning_enabled')->default(false);
            $table->boolean('public_access')->default(false);
            $table->string('status', 20)->default('active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buckets');
    }
};
