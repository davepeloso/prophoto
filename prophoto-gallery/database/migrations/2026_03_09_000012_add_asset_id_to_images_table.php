<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('images')) {
            return;
        }

        Schema::table('images', function (Blueprint $table) {
            if (!Schema::hasColumn('images', 'asset_id')) {
                $table->unsignedBigInteger('asset_id')->nullable()->after('id');
                $table->index('asset_id', 'idx_images_asset_id');
            }
        });

        if (!Schema::hasTable('assets')) {
            return;
        }

        Schema::table('images', function (Blueprint $table) {
            try {
                $table->foreign('asset_id', 'fk_images_asset_id')
                    ->references('id')
                    ->on('assets')
                    ->nullOnDelete();
            } catch (\Throwable) {
                // The FK may already exist, or the driver may not support this operation.
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('images')) {
            return;
        }

        Schema::table('images', function (Blueprint $table) {
            try {
                $table->dropForeign('fk_images_asset_id');
            } catch (\Throwable) {
            }

            try {
                $table->dropIndex('idx_images_asset_id');
            } catch (\Throwable) {
            }

            if (Schema::hasColumn('images', 'asset_id')) {
                $table->dropColumn('asset_id');
            }
        });
    }
};

