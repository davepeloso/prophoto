<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_generated_portraits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_generation_request_id')->constrained('ai_generation_requests')->cascadeOnDelete();
            $table->string('imagekit_file_id')->nullable();
            $table->string('imagekit_url', 1000)->nullable();
            $table->string('imagekit_thumbnail_url', 1000)->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('downloaded_by_subject')->default(false);
            $table->timestamps();

            $table->index('ai_generation_request_id', 'idx_ai_portraits_request');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_generated_portraits');
    }
};
