<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the gallery_comments table for collaboration and feedback.
     * Supports both gallery-level and image-level comments with threading.
     */
    public function up(): void
    {
        Schema::create('gallery_comments', function (Blueprint $table) {
            $table->id();

            // Comment location
            $table->foreignId('gallery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('image_id')
                ->nullable()
                ->constrained('images')
                ->cascadeOnDelete(); // NULL = gallery comment, not image-specific

            // Who commented
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            // Threading support
            $table->foreignId('parent_comment_id')
                ->nullable()
                ->constrained('gallery_comments')
                ->cascadeOnDelete();

            // Comment content
            $table->text('comment_text');

            // Optional annotation data for image markup
            // Example: {x: 100, y: 200, width: 50, height: 50, type: 'circle'}
            $table->json('annotation_data')->nullable();

            // Visibility control
            $table->boolean('is_internal')->default(false); // Internal studio notes vs client visible

            // Resolution tracking (for edit requests/feedback)
            $table->boolean('is_resolved')->default(false);
            $table->foreignId('resolved_by_user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');
            $table->timestamp('resolved_at')->nullable();

            // Mentions (JSON array of user IDs)
            $table->json('mentioned_user_ids')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('gallery_id', 'idx_comments_gallery');
            $table->index('image_id', 'idx_comments_image');
            $table->index('parent_comment_id', 'idx_comments_parent');
            $table->index(['gallery_id', 'is_internal'], 'idx_comments_visibility');
            $table->index(['gallery_id', 'is_resolved'], 'idx_comments_resolved');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gallery_comments');
    }
};
