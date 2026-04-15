<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Story 7.4 — Add viewer_template column to galleries table.
 *
 * Stores the slug of the visual template used for client-facing gallery views.
 * Null = 'default' (resolved at runtime for backwards compatibility).
 *
 * This is a SEPARATE concern from the gallery_templates table, which stores
 * creation-time defaults for gallery configuration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->string('viewer_template')
                ->nullable()
                ->default(null)
                ->after('type')
                ->comment('Viewer template slug (null = default)');
        });
    }

    public function down(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->dropColumn('viewer_template');
        });
    }
};
