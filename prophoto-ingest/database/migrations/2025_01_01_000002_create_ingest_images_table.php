<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingest_images', function (Blueprint $table) {
            $table->id();
            $table->string('file_name');
            $table->string('file_path', 512);
            $table->string('disk', 32)->default('local');
            $table->unsignedBigInteger('size')->default(0);
            $table->string('alt_text')->nullable();

            // Core indexed metadata for fast filtering
            $table->dateTime('date_taken')->nullable()->index();
            $table->string('camera_make', 64)->nullable()->index();
            $table->string('camera_model', 128)->nullable();
            $table->string('lens', 128)->nullable();
            $table->decimal('f_stop', 4, 2)->nullable()->index();
            $table->unsignedInteger('iso')->nullable()->index();
            $table->decimal('shutter_speed', 10, 6)->nullable();
            $table->unsignedSmallInteger('focal_length')->nullable()->index();
            $table->decimal('gps_lat', 10, 8)->nullable();
            $table->decimal('gps_lng', 11, 8)->nullable();

            // Full archival metadata
            $table->json('raw_metadata')->nullable();

            // Polymorphic relation to host app models
            $table->nullableMorphs('imageable');

            $table->timestamps();
        });

        Schema::create('ingest_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('slug', 50)->unique();
            $table->string('color', 7)->nullable(); // Hex color
        });

        Schema::create('ingest_image_tag', function (Blueprint $table) {
            $table->foreignId('image_id')->constrained('ingest_images')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('ingest_tags')->cascadeOnDelete();
            $table->primary(['image_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingest_image_tag');
        Schema::dropIfExists('ingest_tags');
        Schema::dropIfExists('ingest_images');
    }
};
