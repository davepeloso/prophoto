<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingest_tags', function (Blueprint $table) {
            $table->string('tag_type', 20)->default('normal')->after('slug');
            $table->index('tag_type');
        });
    }

    public function down(): void
    {
        Schema::table('ingest_tags', function (Blueprint $table) {
            $table->dropIndex(['tag_type']);
            $table->dropColumn('tag_type');
        });
    }
};
