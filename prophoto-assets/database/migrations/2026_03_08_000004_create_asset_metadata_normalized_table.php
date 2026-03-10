<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_metadata_normalized', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->string('schema_version', 64);
            $table->timestamp('normalized_at')->nullable();
            $table->json('payload');
            $table->string('camera_make', 128)->nullable()->index();
            $table->string('lens', 191)->nullable();
            $table->unsignedInteger('iso')->nullable()->index();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['asset_id', 'schema_version'], 'idx_asset_metadata_normalized_asset_schema');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_metadata_normalized');
    }
};
