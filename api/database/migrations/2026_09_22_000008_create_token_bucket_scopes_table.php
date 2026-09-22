<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('token_bucket_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_token_id')->constrained('api_tokens')->cascadeOnDelete();
            $table->foreignId('bucket_id')->constrained('buckets')->cascadeOnDelete();
            $table->string('path_prefix')->nullable();
            $table->json('permissions');
            $table->timestamps();

            $table->unique(['api_token_id', 'bucket_id', 'path_prefix']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('token_bucket_scopes');
    }
};
