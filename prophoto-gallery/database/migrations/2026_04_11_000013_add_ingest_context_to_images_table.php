<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 6 — Story 1c.6
 *
 * Adds ingest context columns to the images table so that gallery Image
 * records created via the ingest pipeline can be traced back to the
 * originating UploadSession and carry their tag context forward.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('images')) {
            return;
        }

        Schema::table('images', function (Blueprint $table) {
            if (! Schema::hasColumn('images', 'ingest_session_id')) {
                // UUID string — references upload_sessions.id
                $table->string('ingest_session_id', 36)
                    ->nullable()
                    ->after('asset_id')
                    ->comment('Source UploadSession UUID from prophoto-ingest');
            }

            if (! Schema::hasColumn('images', 'ingest_file_id')) {
                // UUID string — references ingest_files.id
                $table->string('ingest_file_id', 36)
                    ->nullable()
                    ->after('ingest_session_id')
                    ->comment('Source IngestFile UUID from prophoto-ingest');
            }

            if (! Schema::hasColumn('images', 'ingest_tags')) {
                // JSON array of tags applied during ingest (metadata + calendar + user)
                $table->json('ingest_tags')
                    ->nullable()
                    ->after('ingest_file_id')
                    ->comment('Tags applied during ingest workflow');
            }

            if (! Schema::hasColumn('images', 'calendar_event_id')) {
                $table->string('calendar_event_id')
                    ->nullable()
                    ->after('ingest_tags')
                    ->comment('Google Calendar event ID linked during ingest');
            }
        });

        // Index for session-based lookups (e.g. "find all images from this ingest session")
        Schema::table('images', function (Blueprint $table) {
            try {
                $table->index('ingest_session_id', 'idx_images_ingest_session');
            } catch (\Throwable) {
                // Index may already exist
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('images')) {
            return;
        }

        Schema::table('images', function (Blueprint $table) {
            try {
                $table->dropIndex('idx_images_ingest_session');
            } catch (\Throwable) {}

            foreach (['calendar_event_id', 'ingest_tags', 'ingest_file_id', 'ingest_session_id'] as $col) {
                if (Schema::hasColumn('images', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
