<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Per-gallery pending type menu. When a gallery is created, the system
     * copies the studio's active templates here so the photographer can
     * enable/disable/reorder per-gallery without touching the master list.
     *
     * The image_approval_states table references gallery_pending_types.id
     * so we have a clean FK for "pending for what?".
     */
    public function up(): void
    {
        Schema::create('gallery_pending_types', function (Blueprint $table) {
            $table->id();

            $table->foreignId('gallery_id')
                ->constrained()
                ->cascadeOnDelete();

            // Back-reference to the template it was copied from (nullable for custom)
            $table->foreignId('template_id')
                ->nullable()
                ->constrained('studio_pending_type_templates')
                ->nullOnDelete();

            $table->string('name');                     // Copied from template, editable per-gallery
            $table->text('description')->nullable();

            $table->string('icon')->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);

            // Only enabled types appear in the proofing modal for this gallery
            $table->boolean('is_enabled')->default(true);

            $table->timestamps();

            $table->index(['gallery_id', 'is_enabled', 'sort_order'], 'idx_gallery_pending_types');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gallery_pending_types');
    }
};
