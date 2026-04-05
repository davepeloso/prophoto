<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add ExifTool support columns to proxy_images table
 *
 * This migration adds:
 * - metadata_raw: Stores the raw ExifTool JSON output for debugging/reference
 * - metadata_error: Stores error message if metadata extraction failed
 * - extraction_method: Records which method was used (exiftool, php_exif, none)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingest_proxy_images', function (Blueprint $table) {
            // Raw ExifTool JSON output (full response for debugging)
            $table->json('metadata_raw')->nullable()->after('metadata');

            // Error message if metadata extraction failed
            $table->string('metadata_error')->nullable()->after('metadata_raw');

            // Extraction method used: 'exiftool', 'php_exif', 'none'
            $table->string('extraction_method', 20)->nullable()->after('metadata_error');

            // Index for finding records with errors
            $table->index('metadata_error');
        });
    }

    public function down(): void
    {
        Schema::table('ingest_proxy_images', function (Blueprint $table) {
            $table->dropIndex(['metadata_error']);
            $table->dropColumn(['metadata_raw', 'metadata_error', 'extraction_method']);
        });
    }
};
