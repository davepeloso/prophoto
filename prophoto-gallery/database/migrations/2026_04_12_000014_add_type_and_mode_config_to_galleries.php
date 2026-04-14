<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds Sprint 2 gallery type system columns:
     *
     *  type        — 'proofing' | 'presentation'
     *                Proofing: identity gate, pipeline, activity ledger, CRUD perms
     *                Presentation: view-only public link, no identity, no pipeline
     *
     *  mode_config — JSON blob holding per-gallery pipeline settings:
     *                {
     *                  "min_approvals": 1,
     *                  "max_approvals": null,    // null = unlimited
     *                  "max_pending": null,
     *                  "ratings_enabled": true,
     *                  "pipeline_sequential": true   // approve required before pending
     *                }
     *
     * Existing rows default to 'proofing' so nothing breaks.
     */
    public function up(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->string('type')
                ->default('proofing')
                ->after('studio_id')
                ->comment('proofing | presentation');

            $table->json('mode_config')
                ->nullable()
                ->after('type')
                ->comment('Pipeline config: min_approvals, max_approvals, max_pending, ratings_enabled, pipeline_sequential');

            $table->index('type', 'idx_galleries_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->dropIndex('idx_galleries_type');
            $table->dropColumn(['type', 'mode_config']);
        });
    }
};
