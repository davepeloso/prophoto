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
        Schema::create('debug_ingest_traces', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->index()->comment('Links to ProxyImage UUID');
            $table->string('session_id', 36)->index()->comment('Groups traces for same upload batch');
            $table->enum('trace_type', [
                'preview_extraction',
                'metadata_extraction',
                'thumbnail_generation',
                'enhancement',
            ])->comment('Type of operation being traced');
            $table->string('method_tried')->comment('Method attempted (e.g., PreviewImage, JpgFromRaw, php_exif)');
            $table->unsignedTinyInteger('method_order')->default(1)->comment('Order in fallback chain (1, 2, 3...)');
            $table->boolean('success')->default(false)->comment('Whether this method succeeded');
            $table->text('failure_reason')->nullable()->comment('Why it failed (null if success)');
            $table->json('result_info')->nullable()->comment('Additional data (size, dimensions, duration_ms)');
            $table->timestamp('created_at')->useCurrent();

            // Composite index for common queries
            $table->index(['uuid', 'trace_type']);
            $table->index(['session_id', 'method_order']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('debug_ingest_traces');
    }
};
