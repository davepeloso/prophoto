<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_metadata_raw', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->string('source', 100);
            $table->string('tool_version', 100)->nullable();
            $table->timestamp('extracted_at')->nullable();
            $table->json('payload');
            $table->string('payload_hash', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['asset_id', 'source'], 'idx_asset_metadata_raw_asset_source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_metadata_raw');
    }
};
