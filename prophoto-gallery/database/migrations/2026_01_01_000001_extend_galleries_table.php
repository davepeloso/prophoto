<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Extends the existing galleries table from prophoto-access with additional fields
     * for the prophoto-galleries package.
     */
    public function up(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            // Cover image for gallery preview
            $table->foreignId('cover_image_id')
                ->nullable()
                ->after('subject_name')
                ->constrained('images')
                ->onDelete('set null');

            // JSON settings for flexible gallery configuration
            $table->json('settings')->nullable()->after('ai_training_status');

            // Custom message from photographer to client
            $table->text('client_message')->nullable()->after('settings');

            // SEO fields for public galleries
            $table->string('seo_title')->nullable()->after('client_message');
            $table->text('seo_description')->nullable()->after('seo_title');

            // Add missing index
            $table->index('session_id', 'idx_galleries_session');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->dropForeign(['cover_image_id']);
            $table->dropIndex('idx_galleries_session');
            $table->dropColumn([
                'cover_image_id',
                'settings',
                'client_message',
                'seo_title',
                'seo_description',
            ]);
        });
    }
};
