<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 7 — Performance Index Migration
 *
 * Adds indexes identified during the N+1 audit and query profiling:
 *
 * upload_sessions:
 *   - idx_upload_sessions_status        — status filtering in previewStatus polling
 *   - idx_upload_sessions_status_studio — dashboard queries: "active sessions for studio"
 *   - idx_upload_sessions_confirmed_at  — ordered confirmation history
 *
 * ingest_files:
 *   - idx_ingest_files_culled           — IngestSessionConfirmedListener: WHERE culled=0
 *   - idx_ingest_files_session_culled   — composite: session_id + culled (listener's main query)
 *   - idx_ingest_files_rating           — gallery sort-by-star queries
 *
 * ingest_image_tags:
 *   - idx_ingest_tags_type_tag          — composite: tag_type + tag (TaggingPanel filter)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── upload_sessions ───────────────────────────────────────────────────
        if (Schema::hasTable('upload_sessions')) {
            Schema::table('upload_sessions', function (Blueprint $table) {
                // Polling queries: WHERE status IN ('confirmed', 'completed')
                try {
                    $table->index('status', 'idx_upload_sessions_status');
                } catch (\Throwable) {}

                // Dashboard: "show all uploading sessions for studio X"
                try {
                    $table->index(['studio_id', 'status'], 'idx_upload_sessions_studio_status');
                } catch (\Throwable) {}

                // Ordered history queries
                try {
                    $table->index('confirmed_at', 'idx_upload_sessions_confirmed_at');
                } catch (\Throwable) {}
            });
        }

        // ── ingest_files ──────────────────────────────────────────────────────
        if (Schema::hasTable('ingest_files')) {
            Schema::table('ingest_files', function (Blueprint $table) {
                // IngestSessionConfirmedListener: WHERE culled = 0 (on confirmed sessions)
                try {
                    $table->index('culled', 'idx_ingest_files_culled');
                } catch (\Throwable) {}

                // The listener's primary query: session_id + upload_status + culled
                // Composite covers all three WHERE clauses in a single index scan
                try {
                    $table->index(
                        ['upload_session_id', 'upload_status', 'culled'],
                        'idx_ingest_files_session_status_culled'
                    );
                } catch (\Throwable) {}

                // Gallery sort-by-star: ORDER BY rating DESC
                try {
                    $table->index('rating', 'idx_ingest_files_rating');
                } catch (\Throwable) {}
            });
        }

        // ── ingest_image_tags ─────────────────────────────────────────────────
        if (Schema::hasTable('ingest_image_tags')) {
            Schema::table('ingest_image_tags', function (Blueprint $table) {
                // TaggingPanel filter: WHERE tag_type = 'metadata' ORDER BY tag
                try {
                    $table->index(['tag_type', 'tag'], 'idx_ingest_tags_type_tag');
                } catch (\Throwable) {}
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('upload_sessions')) {
            Schema::table('upload_sessions', function (Blueprint $table) {
                foreach ([
                    'idx_upload_sessions_status',
                    'idx_upload_sessions_studio_status',
                    'idx_upload_sessions_confirmed_at',
                ] as $idx) {
                    try { $table->dropIndex($idx); } catch (\Throwable) {}
                }
            });
        }

        if (Schema::hasTable('ingest_files')) {
            Schema::table('ingest_files', function (Blueprint $table) {
                foreach ([
                    'idx_ingest_files_culled',
                    'idx_ingest_files_session_status_culled',
                    'idx_ingest_files_rating',
                ] as $idx) {
                    try { $table->dropIndex($idx); } catch (\Throwable) {}
                }
            });
        }

        if (Schema::hasTable('ingest_image_tags')) {
            Schema::table('ingest_image_tags', function (Blueprint $table) {
                try { $table->dropIndex('idx_ingest_tags_type_tag'); } catch (\Throwable) {}
            });
        }
    }
};
