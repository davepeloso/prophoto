<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_session_contexts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('asset_id')->index();
            $table->unsignedBigInteger('session_id')->nullable()->index();
            $table->string('source_decision_id', 191)->unique();
            $table->string('decision_type', 32)->index();
            $table->string('subject_type', 32)->index();
            $table->string('subject_id', 191);
            $table->string('ingest_item_id', 191)->nullable();
            $table->string('confidence_tier', 16)->nullable();
            $table->decimal('confidence_score', 6, 5)->nullable();
            $table->string('algorithm_version', 64);
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->foreign('asset_id')
                ->references('id')
                ->on('assets')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_session_contexts');
    }
};
