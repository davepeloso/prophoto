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
        Schema::create('ai_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gallery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('fine_tune_id')->nullable(); // Astria model ID
            $table->unsignedInteger('training_image_count')->nullable();
            $table->string('model_status', 50)->default('pending'); // pending, training, trained, failed, expired
            $table->decimal('fine_tune_cost', 8, 2)->default(1.50);
            $table->timestamp('model_created_at')->nullable();
            $table->timestamp('model_expires_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('gallery_id', 'idx_ai_generations_gallery');
            $table->index('model_status', 'idx_ai_generations_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_generations');
    }
};
