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
        Schema::create('debug_config_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('User-defined snapshot name');
            $table->text('description')->nullable()->comment('What is being tested');
            $table->json('config_data')->comment('Full ingest configuration snapshot');
            $table->json('queue_config')->comment('Queue priorities, workers, retry settings');
            $table->json('supervisor_config')->nullable()->comment('Supervisor process settings');
            $table->json('environment')->comment('Relevant env vars (sanitized)');
            $table->timestamps();

            $table->index('name');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('debug_config_snapshots');
    }
};
