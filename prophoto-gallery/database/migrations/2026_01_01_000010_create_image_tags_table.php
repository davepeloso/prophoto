<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the image_tags table for categorizing and organizing images.
     */
    public function up(): void
    {
        Schema::create('image_tags', function (Blueprint $table) {
            $table->id();

            // Studio scoping
            $table->foreignId('studio_id')->constrained()->cascadeOnDelete();

            // Tag details
            $table->string('name', 100);
            $table->string('slug', 100);

            // Optional description
            $table->text('description')->nullable();

            // Visual identifier
            $table->string('color', 7)->nullable(); // Hex color #FF5733
            $table->string('icon')->nullable(); // Icon identifier or emoji

            // Tag type/category (for organizing tags)
            $table->string('tag_type')->nullable(); // workflow, style, mood, technical, client

            // Usage tracking
            $table->unsignedInteger('usage_count')->default(0);

            // Audit fields
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            $table->timestamps();
            $table->softDeletes();

            // Unique constraint: tag name must be unique per studio
            $table->unique(['studio_id', 'slug'], 'unique_studio_tag_slug');

            // Indexes
            $table->index('studio_id', 'idx_tags_studio');
            $table->index('tag_type', 'idx_tags_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('image_tags');
    }
};
