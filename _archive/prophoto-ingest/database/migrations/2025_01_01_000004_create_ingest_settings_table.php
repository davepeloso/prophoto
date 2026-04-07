<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ingest_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique()->comment('Configuration key (e.g., schema.path, exif.thumbnail.quality)');
            $table->text('value')->comment('The setting value (can be JSON, string, or number)');
            $table->string('type', 20)->default('string')->comment('Data type: string, integer, boolean, json, float');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ingest_settings');
    }
};
