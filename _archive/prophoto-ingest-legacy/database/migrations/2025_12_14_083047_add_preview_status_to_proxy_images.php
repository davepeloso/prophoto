<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingest_proxy_images', function (Blueprint $table) {
            // Preview generation status: 'pending', 'processing', 'ready', 'failed'
            $table->string('preview_status', 20)->default('pending')->after('preview_path');

            // Track when preview was last attempted (for retry logic)
            $table->timestamp('preview_attempted_at')->nullable()->after('preview_status');

            // Store error message if preview generation fails
            $table->string('preview_error')->nullable()->after('preview_attempted_at');

            // Index for queue worker queries
            $table->index(['preview_status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('ingest_proxy_images', function (Blueprint $table) {
            $table->dropIndex(['preview_status', 'created_at']);
            $table->dropColumn(['preview_status', 'preview_attempted_at', 'preview_error']);
        });
    }
};
