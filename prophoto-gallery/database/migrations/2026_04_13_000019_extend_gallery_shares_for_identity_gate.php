<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 2 — Story 2.1
 *
 * Extends gallery_shares with the columns needed for:
 *   1. Identity gate  — email confirmation before accessing a Proofing gallery
 *   2. Submission     — client submits their final selection
 *   3. Lock state     — photographer or system locks a share token read-only
 *   4. Pipeline overrides — per-share mode_config overrides (Sprint 3+)
 *
 * Architecture notes:
 *   - Additive-only. Existing columns and indexes are untouched.
 *   - confirmed_email may differ from shared_with_email: the share was sent to
 *     one address but accessed from another (both are recorded in the ledger).
 *   - submitted_at and is_locked are share-token scoped, not gallery-scoped —
 *     multiple subjects can have independent submission states on one gallery.
 *   - pipeline_overrides is a nullable JSON blob mirroring the shape of
 *     galleries.mode_config, allowing per-share cap overrides in Phase 3+.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gallery_shares', function (Blueprint $table) {
            // ── Identity gate ───────────────────────────────────────────────
            // The email the subject entered at the gate (may differ from
            // shared_with_email — record both for the activity ledger).
            $table->string('confirmed_email')->nullable()->after('shared_with_email');
            $table->timestamp('identity_confirmed_at')->nullable()->after('confirmed_email');

            // ── Submission state ────────────────────────────────────────────
            // Set when the subject clicks "Submit my selections".
            // After this is set the share token becomes read-only.
            $table->timestamp('submitted_at')->nullable()->after('last_accessed_at');

            // ── Lock state ──────────────────────────────────────────────────
            // Photographer can manually lock any share token from Filament.
            // Also set automatically when submitted_at is populated.
            $table->boolean('is_locked')->default(false)->after('submitted_at');

            // ── Per-share pipeline overrides (Sprint 3+) ────────────────────
            // Optional JSON mirroring galleries.mode_config shape.
            // When set, these values take precedence over gallery-level config
            // for this share token only (e.g. different max_approvals per subject).
            $table->json('pipeline_overrides')->nullable()->after('message');
        });
    }

    public function down(): void
    {
        Schema::table('gallery_shares', function (Blueprint $table) {
            $table->dropColumn([
                'confirmed_email',
                'identity_confirmed_at',
                'submitted_at',
                'is_locked',
                'pipeline_overrides',
            ]);
        });
    }
};
