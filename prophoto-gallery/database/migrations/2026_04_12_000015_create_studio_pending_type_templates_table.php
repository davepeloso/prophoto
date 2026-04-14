<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Studio-level master list of pending types. The photographer manages
     * this list once from their Filament admin and it becomes the menu of
     * options that appears in gallery_pending_types (per-gallery copies).
     *
     * Default templates (seeded):
     *   - Retouch
     *   - Background Swap
     *   - Awaiting Second Approval
     *   - Colour Correction
     *
     * The photographer can add custom types (e.g., "Hair & Makeup Touch-up",
     * "Object Removal") or reorder/hide defaults.
     */
    public function up(): void
    {
        Schema::create('studio_pending_type_templates', function (Blueprint $table) {
            $table->id();

            // studio_id = null means it's a system default (visible to all studios)
            $table->foreignId('studio_id')
                ->nullable()
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name');                         // e.g. "Retouch"
            $table->text('description')->nullable();        // Tooltip / help text

            // Visual — optional icon slug from heroicons or lucide
            $table->string('icon')->nullable();             // e.g. 'pencil-square'

            // Sort order within the studio's list
            $table->unsignedSmallInteger('sort_order')->default(0);

            // System defaults (studio_id = null) cannot be deleted, only hidden
            $table->boolean('is_system_default')->default(false);

            // Studio can hide a system default without deleting it
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['studio_id', 'is_active', 'sort_order'], 'idx_pending_templates_studio');
            $table->unique(['studio_id', 'name'], 'uniq_pending_template_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('studio_pending_type_templates');
    }
};
