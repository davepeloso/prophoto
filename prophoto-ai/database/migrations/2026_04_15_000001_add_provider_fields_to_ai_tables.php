<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Story 8.5 — Add provider-agnostic fields to AI tables.
 *
 * These columns sit alongside the existing Astria-specific columns (fine_tune_id, etc.).
 * The provider abstraction uses these new columns; existing columns are preserved
 * for backward compatibility and Astria-specific mapping.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_generations', function (Blueprint $table) {
            $table->string('provider_key')->default('astria')->after('gallery_id');
            $table->string('external_model_id')->nullable()->after('fine_tune_id');
            $table->json('provider_metadata')->nullable()->after('error_message');
        });

        Schema::table('ai_generation_requests', function (Blueprint $table) {
            $table->string('provider_key')->default('astria')->after('ai_generation_id');
            $table->string('external_request_id')->nullable()->after('request_number');
            $table->json('provider_metadata')->nullable()->after('error_message');
        });

        Schema::table('ai_generated_portraits', function (Blueprint $table) {
            $table->string('storage_driver')->default('imagekit')->after('ai_generation_request_id');
            $table->string('original_provider_url', 1000)->nullable()->after('imagekit_thumbnail_url');
        });
    }

    public function down(): void
    {
        Schema::table('ai_generations', function (Blueprint $table) {
            $table->dropColumn(['provider_key', 'external_model_id', 'provider_metadata']);
        });

        Schema::table('ai_generation_requests', function (Blueprint $table) {
            $table->dropColumn(['provider_key', 'external_request_id', 'provider_metadata']);
        });

        Schema::table('ai_generated_portraits', function (Blueprint $table) {
            $table->dropColumn(['storage_driver', 'original_provider_url']);
        });
    }
};
