<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Extends the existing images table from prophoto-access with additional fields
     * for enhanced image management.
     */
    public function up(): void
    {
        Schema::table('images', function (Blueprint $table) {
            // Store original filename before sanitization
            $table->string('original_filename')->nullable()->after('filename');

            // Image metadata
            $table->string('title')->nullable()->after('filename');
            $table->text('caption')->nullable()->after('title');
            $table->string('alt_text')->nullable()->after('caption');

            // Image flags
            $table->boolean('is_featured')->default(false)->after('sort_order');
            $table->boolean('is_client_favorite')->default(false)->after('is_featured');

            // File hash for duplicate detection
            $table->string('hash', 64)->nullable()->after('mime_type');

            // Indexes for performance
            $table->index('hash', 'idx_images_hash');
            $table->index(['gallery_id', 'is_featured'], 'idx_images_featured');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('images', function (Blueprint $table) {
            $table->dropIndex('idx_images_hash');
            $table->dropIndex('idx_images_featured');
            $table->dropColumn([
                'original_filename',
                'title',
                'caption',
                'alt_text',
                'is_featured',
                'is_client_favorite',
                'hash',
            ]);
        });
    }
};
