<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingest_proxy_images', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('filename');
            $table->string('temp_path');
            $table->string('thumbnail_path')->nullable();
            $table->boolean('is_culled')->default(false);
            $table->boolean('is_starred')->default(false);
            $table->unsignedTinyInteger('rating')->default(0);
            $table->smallInteger('rotation')->default(0);
            $table->unsignedSmallInteger('order_index')->default(0);
            $table->json('metadata')->nullable();
            $table->json('tags_json')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_culled']);
            $table->index(['user_id', 'order_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingest_proxy_images');
    }
};
