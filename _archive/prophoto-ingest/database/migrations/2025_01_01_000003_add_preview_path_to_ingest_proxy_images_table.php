<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingest_proxy_images', function (Blueprint $table) {
            $table->string('preview_path')->nullable()->after('thumbnail_path');
        });
    }

    public function down(): void
    {
        Schema::table('ingest_proxy_images', function (Blueprint $table) {
            $table->dropColumn('preview_path');
        });
    }
};
