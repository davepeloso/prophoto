<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the gallery_collections table for grouping galleries into albums/portfolios.
     */
    public function up(): void
    {
        Schema::create('gallery_collections', function (Blueprint $table) {
            $table->id();

            // Tenant scoping (follows prophoto-access pattern)
            $table->foreignId('studio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            // Collection details
            $table->string('name');
            $table->text('description')->nullable();

            // Cover image for collection preview
            $table->foreignId('cover_image_id')
                ->nullable()
                ->constrained('images')
                ->onDelete('set null');

            // Display ordering
            $table->unsignedInteger('sort_order')->default(0);

            // Visibility
            $table->boolean('is_public')->default(false);

            // Settings (JSON for extensibility)
            $table->json('settings')->nullable();

            // SEO fields
            $table->string('slug')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();

            // Audit fields
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('organization_id', 'idx_collections_organization');
            $table->index('studio_id', 'idx_collections_studio');
            $table->index(['studio_id', 'is_public'], 'idx_collections_public');
            $table->unique(['studio_id', 'slug'], 'unique_studio_slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gallery_collections');
    }
};
