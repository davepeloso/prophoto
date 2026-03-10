<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('asset_metadata_normalized')) {
            return;
        }

        Schema::table('asset_metadata_normalized', function (Blueprint $table) {
            if (!Schema::hasColumn('asset_metadata_normalized', 'media_kind')) {
                $table->string('media_kind', 32)->nullable()->after('schema_version');
                $table->index('media_kind', 'idx_asset_metadata_normalized_media_kind');
            }

            if (!Schema::hasColumn('asset_metadata_normalized', 'captured_at')) {
                $table->timestamp('captured_at')->nullable()->after('normalized_at');
                $table->index('captured_at', 'idx_asset_metadata_normalized_captured_at');
            }

            if (!Schema::hasColumn('asset_metadata_normalized', 'mime_type')) {
                $table->string('mime_type', 191)->nullable()->after('camera_make');
                $table->index('mime_type', 'idx_asset_metadata_normalized_mime_type');
            }

            if (!Schema::hasColumn('asset_metadata_normalized', 'file_size')) {
                $table->unsignedBigInteger('file_size')->nullable()->after('mime_type');
            }

            if (!Schema::hasColumn('asset_metadata_normalized', 'camera_model')) {
                $table->string('camera_model', 191)->nullable()->after('camera_make');
            }

            if (!Schema::hasColumn('asset_metadata_normalized', 'exif_orientation')) {
                $table->unsignedSmallInteger('exif_orientation')->nullable()->after('height');
            }

            if (!Schema::hasColumn('asset_metadata_normalized', 'rating')) {
                $table->unsignedTinyInteger('rating')->nullable()->after('iso');
                $table->index('rating', 'idx_asset_metadata_normalized_rating');
            }

            if (!Schema::hasColumn('asset_metadata_normalized', 'color_profile')) {
                $table->string('color_profile', 191)->nullable()->after('lens');
            }

            if (!Schema::hasColumn('asset_metadata_normalized', 'page_count')) {
                $table->unsignedInteger('page_count')->nullable()->after('color_profile');
            }

            if (!Schema::hasColumn('asset_metadata_normalized', 'duration_seconds')) {
                $table->decimal('duration_seconds', 12, 4)->nullable()->after('page_count');
            }

            if (!Schema::hasColumn('asset_metadata_normalized', 'has_gps')) {
                $table->boolean('has_gps')->default(false)->after('duration_seconds');
                $table->index('has_gps', 'idx_asset_metadata_normalized_has_gps');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('asset_metadata_normalized')) {
            return;
        }

        Schema::table('asset_metadata_normalized', function (Blueprint $table) {
            $dropIndexIfExists = static function (string $name) use ($table): void {
                try {
                    $table->dropIndex($name);
                } catch (\Throwable) {
                }
            };

            $dropIndexIfExists('idx_asset_metadata_normalized_media_kind');
            $dropIndexIfExists('idx_asset_metadata_normalized_captured_at');
            $dropIndexIfExists('idx_asset_metadata_normalized_mime_type');
            $dropIndexIfExists('idx_asset_metadata_normalized_rating');
            $dropIndexIfExists('idx_asset_metadata_normalized_has_gps');

            $columns = [
                'media_kind',
                'captured_at',
                'mime_type',
                'file_size',
                'camera_model',
                'exif_orientation',
                'rating',
                'color_profile',
                'page_count',
                'duration_seconds',
                'has_gps',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('asset_metadata_normalized', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

