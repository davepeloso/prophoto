<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingest_proxy_image_tag', function (Blueprint $table) {
            $table->foreignId('proxy_image_id')->constrained('ingest_proxy_images')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('ingest_tags')->cascadeOnDelete();
            $table->primary(['proxy_image_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingest_proxy_image_tag');
    }
};
