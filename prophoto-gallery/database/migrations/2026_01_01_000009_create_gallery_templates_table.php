<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the gallery_templates table for reusable gallery configurations.
     */
    public function up(): void
    {
        Schema::create('gallery_templates', function (Blueprint $table) {
            $table->id();

            // Studio scoping
            $table->foreignId('studio_id')->constrained()->cascadeOnDelete();

            // Template details
            $table->string('name');
            $table->text('description')->nullable();

            // Default settings to apply to new galleries
            // Matches structure of galleries.settings JSON field
            $table->json('default_settings');

            // Optional watermark configuration
            $table->json('watermark_settings')->nullable();

            // Optional download configuration
            $table->json('download_settings')->nullable();

            // Default permissions for magic link users
            // Example: ['view_gallery', 'rate_images', 'download_images']
            $table->json('guest_permissions')->nullable();

            // Template type/category
            $table->string('template_type')->nullable(); // headshot, event, portrait, commercial

            // Is this the default template?
            $table->boolean('is_default')->default(false);

            // Usage tracking
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamp('last_used_at')->nullable();

            // Audit fields
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('studio_id', 'idx_templates_studio');
            $table->index(['studio_id', 'is_default'], 'idx_templates_default');
            $table->index('template_type', 'idx_templates_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gallery_templates');
    }
};
