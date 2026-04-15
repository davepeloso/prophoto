<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 2 — Story 2.1
 *
 * Append-only activity ledger for all attributed actions within a Gallery.
 * Every user-visible action (approve, rate, comment, add/remove image, submit,
 * lock, download, version upload) writes one row here.
 *
 * Architecture notes:
 *   - Owned entirely by prophoto-gallery. No cross-package FKs.
 *   - Intentionally has no updated_at — rows are never modified after insert.
 *   - actor_email is denormalised — share identities are not system users.
 *   - The GalleryActivityLogger service (Sprint 4) is the single write path.
 *   - metadata JSON carries action-specific context (e.g. version numbers,
 *     pending type names, download resolution) without schema changes.
 *
 * action_type vocabulary (enforce at service layer):
 *   image_added | image_removed | approved | approved_pending | cleared |
 *   rated | commented | version_uploaded | gallery_submitted | gallery_locked |
 *   download | identity_confirmed
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gallery_activity_log', function (Blueprint $table) {
            $table->id();

            $table->foreignId('gallery_id')
                  ->constrained('galleries')
                  ->cascadeOnDelete();

            // Null = action not tied to a specific share (e.g. photographer adding images)
            $table->foreignId('gallery_share_id')
                  ->nullable()
                  ->constrained('gallery_shares')
                  ->nullOnDelete();

            // Null = gallery-level action (submit, lock, etc.)
            $table->foreignId('image_id')
                  ->nullable()
                  ->constrained('images')
                  ->nullOnDelete();

            $table->string('action_type', 50);

            // studio_user | share_identity
            $table->string('actor_type', 30);

            // Denormalised — null only for system-initiated actions
            $table->string('actor_email')->nullable();

            // Action-specific payload: version numbers, pending type, resolution, etc.
            $table->json('metadata')->nullable();

            // Indexed for ledger queries and time-range filtering
            $table->timestamp('occurred_at')->useCurrent();

            // created_at only — no updated_at (append-only)
            $table->timestamp('created_at')->useCurrent();

            $table->index(['gallery_id', 'occurred_at']);
            $table->index(['gallery_id', 'action_type']);
            $table->index(['gallery_id', 'gallery_share_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_activity_log');
    }
};
