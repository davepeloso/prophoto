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
        Schema::create('staging_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')->constrained()->cascadeOnDelete();
            $table->uuid('batch_id'); // UUID for upload batch
            $table->string('filename');
            $table->string('original_path', 500);
            $table->string('thumbnail_path', 500)->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('assigned_to_gallery_id')->nullable()->constrained('galleries')->onDelete('set null');
            $table->timestamp('assigned_at')->nullable();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->index('batch_id', 'idx_staging_images_batch');
            $table->index('assigned_to_gallery_id', 'idx_staging_images_assigned');
            $table->index('created_at', 'idx_staging_images_created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staging_images');
    }
};
