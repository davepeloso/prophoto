<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the pivot table for many-to-many relationship between collections and galleries.
     */
    public function up(): void
    {
        Schema::create('collection_gallery', function (Blueprint $table) {
            $table->id();

            // Many-to-many relationship
            $table->foreignId('collection_id')
                ->constrained('gallery_collections')
                ->cascadeOnDelete();

            $table->foreignId('gallery_id')
                ->constrained('galleries')
                ->cascadeOnDelete();

            // Display ordering within collection
            $table->unsignedInteger('sort_order')->default(0);

            // When was gallery added to collection
            $table->timestamp('added_at')->useCurrent();

            // Optional: who added it
            $table->foreignId('added_by_user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            // Unique constraint: gallery can only be in collection once
            $table->unique(['collection_id', 'gallery_id'], 'unique_collection_gallery');

            // Index for reverse lookup
            $table->index('gallery_id', 'idx_collection_gallery_gallery');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collection_gallery');
    }
};
