<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingest_proxy_images', function (Blueprint $table) {
            // Track preview dimensions for enhancement
            $table->unsignedSmallInteger('preview_width')->nullable()->after('preview_path');
            $table->string('enhancement_status', 20)->default('none')->after('preview_width');
            $table->timestamp('enhancement_requested_at')->nullable()->after('enhancement_status');
        });
    }

    public function down(): void
    {
        Schema::table('ingest_proxy_images', function (Blueprint $table) {
            $table->dropColumn(['preview_width', 'enhancement_status', 'enhancement_requested_at']);
        });
    }
};
