<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the pivot table for many-to-many relationship between images and tags.
     */
    public function up(): void
    {
        Schema::create('image_tag', function (Blueprint $table) {
            $table->id();

            // Many-to-many relationship
            $table->foreignId('image_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('image_tags')->cascadeOnDelete();

            // Who tagged it (for audit trail)
            $table->foreignId('tagged_by_user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            // When was it tagged
            $table->timestamp('tagged_at')->useCurrent();

            // Optional: tag-specific metadata
            // Example: {confidence: 0.95} for AI-suggested tags
            $table->json('metadata')->nullable();

            // Unique constraint: image can only have tag once
            $table->unique(['image_id', 'tag_id'], 'unique_image_tag');

            // Index for reverse lookup (find all images with tag)
            $table->index('tag_id', 'idx_image_tag_tag');

            // Index for audit queries
            $table->index('tagged_by_user_id', 'idx_image_tag_user');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('image_tag');
    }
};
