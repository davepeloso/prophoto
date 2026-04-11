<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the upload_sessions table.
 *
 * An upload session is created when a user initiates a batch upload
 * via the ingest interface. It tracks the lifecycle from initiation
 * through calendar matching, file uploading, tagging, and final
 * confirmation that triggers the SessionAssociationResolved event.
 *
 * Stories 1a.5 + 1a.6 — Sprint 1
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('upload_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // Ownership
            $table->unsignedBigInteger('studio_id');
            $table->unsignedBigInteger('user_id');

            // Calendar matching result (nullable — user may skip)
            $table->string('calendar_event_id', 191)->nullable();
            $table->string('calendar_provider', 32)->nullable();
            $table->decimal('calendar_match_confidence', 5, 4)->nullable();
            $table->json('calendar_match_evidence')->nullable();

            // Aggregate upload stats (updated as files arrive)
            $table->unsignedInteger('file_count')->default(0);
            $table->unsignedInteger('completed_file_count')->default(0);
            $table->unsignedBigInteger('total_size_bytes')->default(0);

            // Lifecycle status
            $table->enum('status', [
                'initiated',   // Session created, metadata extracted
                'matching',    // Calendar matching in progress
                'uploading',   // Files are being transferred
                'tagging',     // User is tagging in gallery
                'confirmed',   // User clicked "Confirm & Continue"
                'completed',   // Assets created, gallery ready
                'failed',      // Unrecoverable error
                'cancelled',   // User abandoned the session
            ])->default('initiated');

            // Downstream associations (populated as session progresses)
            $table->unsignedBigInteger('gallery_id')->nullable();

            // Timing
            $table->timestamp('upload_started_at')->nullable();
            $table->timestamp('upload_completed_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps(); // created_at = session initiated_at

            // Indexes for common query patterns
            $table->index(['studio_id', 'user_id'], 'idx_upload_sessions_studio_user');
            $table->index(['status', 'created_at'], 'idx_upload_sessions_status_created');
            $table->index('gallery_id', 'idx_upload_sessions_gallery');

            // Foreign keys
            $table->foreign('studio_id', 'fk_upload_sessions_studio')
                ->references('id')
                ->on('studios')
                ->cascadeOnDelete();

            $table->foreign('user_id', 'fk_upload_sessions_user')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->foreign('gallery_id', 'fk_upload_sessions_gallery')
                ->references('id')
                ->on('galleries')
                ->nullOnDelete();
        });

        Schema::create('ingest_files', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('upload_session_id');

            // Populated after asset creation is complete
            $table->unsignedBigInteger('asset_id')->nullable();

            // File metadata (from browser-extracted EXIF)
            $table->string('original_filename', 255);
            $table->unsignedBigInteger('file_size_bytes');
            $table->string('file_type', 16);    // 'jpg', 'raw', 'dng', 'tiff', etc.
            $table->string('mime_type', 128)->nullable();

            // Raw EXIF data extracted client-side
            // Stored as JSON for flexibility (fields vary by camera/format)
            $table->json('exif_data')->nullable();

            // Upload progress
            $table->enum('upload_status', [
                'pending',
                'uploading',
                'completed',
                'failed',
            ])->default('pending');

            $table->timestamp('uploaded_at')->nullable();

            // Ingest decisions
            $table->boolean('culled')->default(false);
            $table->timestamps();

            // Indexes
            $table->index(
                ['upload_session_id', 'upload_status'],
                'idx_ingest_files_session_status'
            );
            $table->index('asset_id', 'idx_ingest_files_asset');

            // Foreign keys
            $table->foreign('upload_session_id', 'fk_ingest_files_session')
                ->references('id')
                ->on('upload_sessions')
                ->cascadeOnDelete();

            $table->foreign('asset_id', 'fk_ingest_files_asset')
                ->references('id')
                ->on('assets')
                ->nullOnDelete();
        });

        Schema::create('ingest_image_tags', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('ingest_file_id');

            $table->string('tag', 100);

            // Where did this tag come from?
            $table->enum('tag_type', [
                'metadata',  // Auto-derived from EXIF (ISO 400, f/1.8, Canon 5D)
                'calendar',  // Auto-derived from matched calendar event
                'user',      // Manually entered by photographer
            ])->default('user');

            $table->timestamp('created_at')->useCurrent();

            // Indexes
            $table->index('ingest_file_id', 'idx_ingest_tags_file');
            $table->index('tag', 'idx_ingest_tags_tag');

            // A file can only have each tag once
            $table->unique(['ingest_file_id', 'tag'], 'uq_ingest_file_tag');

            $table->foreign('ingest_file_id', 'fk_ingest_tags_file')
                ->references('id')
                ->on('ingest_files')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        // Drop in reverse dependency order
        Schema::dropIfExists('ingest_image_tags');
        Schema::dropIfExists('ingest_files');
        Schema::dropIfExists('upload_sessions');
    }
};
