<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bucket_user_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bucket_id')->constrained('buckets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('can_list')->default(false);
            $table->boolean('can_read')->default(false);
            $table->boolean('can_write')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->string('path_prefix')->nullable();
            $table->timestamps();

            $table->unique(['bucket_id', 'user_id', 'path_prefix']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bucket_user_permissions');
    }
};
