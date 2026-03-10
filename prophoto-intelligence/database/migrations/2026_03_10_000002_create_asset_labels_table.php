<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignId('run_id')->constrained('intelligence_runs')->cascadeOnDelete();
            $table->string('label', 191);
            $table->decimal('confidence', 5, 4)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['run_id', 'label'], 'uq_asset_labels_run_label');
            $table->index('asset_id', 'idx_labels_asset');
            $table->index('run_id', 'idx_labels_run');
            $table->index(['asset_id', 'label'], 'idx_labels_asset_label');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_labels');
    }
};
