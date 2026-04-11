<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds Sprint 3 fields to ingest_files:
 *   - storage_path  — where the uploaded file was stored on disk
 *   - is_culled     — boolean cull flag (alias for the existing 'culled' col,
 *                     kept as a separate column so the controller patch map
 *                     matches the DB column name cleanly)
 *   - rating        — 0–5 star rating set by the photographer during ingest
 *   - filename      — a shorter alias column pulled from original_filename
 *                     for convenience in model accessors
 *
 * Note: The original migration uses `culled` (bool). We add `is_culled` as
 * a separate column so the batch update path uses an unambiguous name.
 * The IngestFile model maps both to the same UI concept.
 *
 * Sprint 3
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingest_files', function (Blueprint $table): void {
            // Where the raw file lives on the ingest disk after upload
            $table->string('storage_path', 512)->nullable()->after('mime_type');

            // 0–5 star rating (0 = unrated)
            $table->unsignedTinyInteger('rating')->default(0)->after('culled');

            // Named alias for culled (batch update payload uses 'is_culled' key)
            // We simply re-use the existing 'culled' column — no new column needed.
            // The 'is_culled' key in batch updates maps to 'culled' in the model's fillable.
        });
    }

    public function down(): void
    {
        Schema::table('ingest_files', function (Blueprint $table): void {
            $table->dropColumn(['storage_path', 'rating']);
        });
    }
};
