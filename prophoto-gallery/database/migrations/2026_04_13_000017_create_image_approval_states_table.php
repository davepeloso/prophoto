<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 2 — Story 2.1
 *
 * Records the approval state of a single Image within a Gallery, attributed
 * to a specific share-token holder (or the studio photographer when
 * gallery_share_id is null).
 *
 * Architecture notes:
 *   - Owned entirely by prophoto-gallery. No cross-package FKs.
 *   - References images.id (same package) and gallery_shares.id (same package).
 *   - References gallery_pending_types.id for the optional pending sub-type.
 *   - actor_email is denormalised — share identities are not system users.
 *   - status transitions enforced at the service layer, not the DB.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('image_approval_states', function (Blueprint $table) {
            $table->id();

            $table->foreignId('gallery_id')
                  ->constrained('galleries')
                  ->cascadeOnDelete();

            $table->foreignId('image_id')
                  ->constrained('images')
                  ->cascadeOnDelete();

            // Null = action taken by the studio photographer directly
            $table->foreignId('gallery_share_id')
                  ->nullable()
                  ->constrained('gallery_shares')
                  ->nullOnDelete();

            // unapproved | approved | approved_pending | cleared
            $table->string('status', 30)->default('unapproved');

            // Set only when status = approved_pending
            $table->foreignId('pending_type_id')
                  ->nullable()
                  ->constrained('gallery_pending_types')
                  ->nullOnDelete();

            // Optional free-text note accompanying a pending request (max 500 chars)
            $table->text('pending_note')->nullable();

            // Denormalised from share identity — not a FK to users
            $table->string('actor_email')->nullable();

            $table->timestamp('set_at')->useCurrent();
            $table->timestamps();

            // Fast lookups for the approval grid and constraint checks
            $table->index(['gallery_id', 'image_id']);
            $table->index(['gallery_id', 'gallery_share_id']);
            $table->index(['gallery_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('image_approval_states');
    }
};
