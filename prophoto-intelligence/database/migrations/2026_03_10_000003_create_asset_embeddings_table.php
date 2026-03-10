<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_embeddings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignId('run_id')->constrained('intelligence_runs')->cascadeOnDelete();
            $table->json('embedding_vector');
            $table->unsignedInteger('vector_dimensions');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['asset_id', 'run_id'], 'uq_asset_embeddings_asset_run');
            $table->index('asset_id', 'idx_embeddings_asset');
            $table->index('run_id', 'idx_embeddings_run');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_embeddings');
    }
};
