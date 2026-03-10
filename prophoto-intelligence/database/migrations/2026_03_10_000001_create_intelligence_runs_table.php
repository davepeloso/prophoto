<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intelligence_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->string('generator_type', 128);
            $table->string('generator_version', 64);
            $table->string('model_name', 191);
            $table->string('model_version', 64);
            $table->string('configuration_hash', 128);
            $table->string('run_scope', 32)->default('single_asset');
            $table->string('run_status', 32)->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_code', 100)->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->unsignedInteger('retry_count')->default(0);
            $table->string('trigger_source', 64)->nullable();
            $table->timestamps();

            $table->index(['asset_id', 'created_at'], 'idx_runs_asset_created');
            $table->index('run_status', 'idx_runs_status');
            $table->index(['asset_id', 'run_status'], 'idx_runs_asset_status');
            $table->index(
                ['asset_id', 'generator_type', 'generator_version', 'model_name', 'model_version', 'run_status', 'completed_at'],
                'idx_runs_asset_model_status_completed'
            );
            $table->index(['asset_id', 'configuration_hash', 'run_status'], 'idx_runs_asset_config_status');
        });

        // Enforce one active run per generator/model tuple where supported.
        $driver = DB::getDriverName();
        if (in_array($driver, ['sqlite', 'pgsql'], true)) {
            DB::statement(
                "CREATE UNIQUE INDEX uq_intelligence_runs_active_tuple ON intelligence_runs
                (asset_id, generator_type, generator_version, model_name, model_version)
                WHERE run_status IN ('pending', 'running')"
            );
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement(
                "ALTER TABLE intelligence_runs
                ADD COLUMN active_concurrency_key VARCHAR(1024)
                GENERATED ALWAYS AS (
                    CASE
                        WHEN run_status IN ('pending','running')
                            THEN CONCAT(asset_id, '|', generator_type, '|', generator_version, '|', model_name, '|', model_version)
                        ELSE NULL
                    END
                ) STORED"
            );
            DB::statement('CREATE UNIQUE INDEX uq_intelligence_runs_active_tuple ON intelligence_runs (active_concurrency_key)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('intelligence_runs');
    }
};
