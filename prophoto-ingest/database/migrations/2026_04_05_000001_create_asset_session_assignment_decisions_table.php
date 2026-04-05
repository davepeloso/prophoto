<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ProPhoto\Contracts\Enums\SessionAssignmentDecisionType;
use ProPhoto\Contracts\Enums\SessionAssociationSubjectType;
use ProPhoto\Contracts\Enums\SessionMatchConfidenceTier;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_session_assignment_decisions', function (Blueprint $table): void {
            $table->id();
            $table->enum('decision_type', $this->decisionTypeValues());
            $table->enum('subject_type', $this->subjectTypeValues());
            $table->string('subject_id', 191);
            $table->string('ingest_item_id', 191)->nullable();
            $table->unsignedBigInteger('asset_id')->nullable();
            $table->unsignedBigInteger('selected_session_id')->nullable();
            $table->enum('confidence_tier', $this->confidenceTierValues())->nullable();
            $table->decimal('confidence_score', 6, 5)->nullable();
            $table->string('algorithm_version', 64);
            $table->enum('trigger_source', [
                'ingest_batch',
                'post_canonicalization',
                'manual_override',
                'manual_reprocess',
                'api',
            ]);
            $table->json('evidence_payload');
            $table->json('ranked_candidates_payload')->nullable();
            $table->enum('calendar_context_state', ['normal', 'stale', 'conflict', 'sync_error'])->nullable();
            $table->string('manual_override_reason_code', 64)->nullable();
            $table->text('manual_override_note')->nullable();
            $table->enum('lock_effect', ['none', 'lock_assigned', 'lock_unassigned', 'unlock'])->default('none');
            $table->unsignedBigInteger('supersedes_decision_id')->nullable();
            $table->string('idempotency_key', 191)->nullable();
            $table->enum('actor_type', ['system', 'user'])->default('system');
            $table->string('actor_id', 191)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id', 'created_at'], 'idx_asad_subject_created_at');
            $table->index('asset_id', 'idx_asad_asset_id');
            $table->index('selected_session_id', 'idx_asad_selected_session_id');
            $table->index('supersedes_decision_id', 'idx_asad_supersedes_decision_id');
            $table->index(['decision_type', 'created_at'], 'idx_asad_decision_type_created_at');
            $table->unique('idempotency_key', 'uq_asad_idempotency_key');

            $table->foreign('asset_id', 'fk_asad_asset_id')
                ->references('id')
                ->on('assets')
                ->nullOnDelete();

            $table->foreign('selected_session_id', 'fk_asad_selected_session_id')
                ->references('id')
                ->on('sessions')
                ->nullOnDelete();

            $table->foreign('supersedes_decision_id', 'fk_asad_supersedes_decision_id')
                ->references('id')
                ->on('asset_session_assignment_decisions')
                ->nullOnDelete();
        });

        $this->applyDecisionConsistencyRules();
    }

    public function down(): void
    {
        $this->dropDecisionConsistencyRules();
        Schema::dropIfExists('asset_session_assignment_decisions');
    }

    /**
     * @return list<string>
     */
    protected function decisionTypeValues(): array
    {
        return array_map(
            static fn (SessionAssignmentDecisionType $type): string => $type->value,
            SessionAssignmentDecisionType::cases()
        );
    }

    /**
     * @return list<string>
     */
    protected function subjectTypeValues(): array
    {
        return array_map(
            static fn (SessionAssociationSubjectType $type): string => $type->value,
            SessionAssociationSubjectType::cases()
        );
    }

    /**
     * @return list<string>
     */
    protected function confidenceTierValues(): array
    {
        return array_map(
            static fn (SessionMatchConfidenceTier $tier): string => $tier->value,
            SessionMatchConfidenceTier::cases()
        );
    }

    protected function applyDecisionConsistencyRules(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement(<<<'SQL'
                CREATE TRIGGER trg_asad_subject_identity_insert
                BEFORE INSERT ON asset_session_assignment_decisions
                FOR EACH ROW
                BEGIN
                    SELECT CASE
                        WHEN NEW.subject_type = 'ingest_item' AND NEW.ingest_item_id IS NULL
                            THEN RAISE(ABORT, 'subject_type ingest_item requires ingest_item_id')
                        WHEN NEW.subject_type = 'asset' AND NEW.asset_id IS NULL
                            THEN RAISE(ABORT, 'subject_type asset requires asset_id')
                    END;
                END;
            SQL);

            DB::statement(<<<'SQL'
                CREATE TRIGGER trg_asad_subject_identity_update
                BEFORE UPDATE ON asset_session_assignment_decisions
                FOR EACH ROW
                BEGIN
                    SELECT CASE
                        WHEN NEW.subject_type = 'ingest_item' AND NEW.ingest_item_id IS NULL
                            THEN RAISE(ABORT, 'subject_type ingest_item requires ingest_item_id')
                        WHEN NEW.subject_type = 'asset' AND NEW.asset_id IS NULL
                            THEN RAISE(ABORT, 'subject_type asset requires asset_id')
                    END;
                END;
            SQL);

            DB::statement(<<<'SQL'
                CREATE TRIGGER trg_asad_selected_session_insert
                BEFORE INSERT ON asset_session_assignment_decisions
                FOR EACH ROW
                BEGIN
                    SELECT CASE
                        WHEN NEW.decision_type IN ('auto_assign', 'propose', 'manual_assign')
                            AND NEW.selected_session_id IS NULL
                            THEN RAISE(ABORT, 'selected_session_id required for assignment/proposal decisions')
                        WHEN NEW.decision_type IN ('no_match', 'manual_unassign')
                            AND NEW.selected_session_id IS NOT NULL
                            THEN RAISE(ABORT, 'selected_session_id must be null for no_match/manual_unassign decisions')
                    END;
                END;
            SQL);

            DB::statement(<<<'SQL'
                CREATE TRIGGER trg_asad_selected_session_update
                BEFORE UPDATE ON asset_session_assignment_decisions
                FOR EACH ROW
                BEGIN
                    SELECT CASE
                        WHEN NEW.decision_type IN ('auto_assign', 'propose', 'manual_assign')
                            AND NEW.selected_session_id IS NULL
                            THEN RAISE(ABORT, 'selected_session_id required for assignment/proposal decisions')
                        WHEN NEW.decision_type IN ('no_match', 'manual_unassign')
                            AND NEW.selected_session_id IS NOT NULL
                            THEN RAISE(ABORT, 'selected_session_id must be null for no_match/manual_unassign decisions')
                    END;
                END;
            SQL);

            return;
        }

        if (in_array($driver, ['pgsql', 'mysql'], true)) {
            DB::statement(<<<'SQL'
                ALTER TABLE asset_session_assignment_decisions
                ADD CONSTRAINT chk_asad_subject_identity
                CHECK (
                    (subject_type = 'ingest_item' AND ingest_item_id IS NOT NULL)
                    OR (subject_type = 'asset' AND asset_id IS NOT NULL)
                )
            SQL);

            DB::statement(<<<'SQL'
                ALTER TABLE asset_session_assignment_decisions
                ADD CONSTRAINT chk_asad_selected_session
                CHECK (
                    (
                        decision_type IN ('auto_assign', 'propose', 'manual_assign')
                        AND selected_session_id IS NOT NULL
                    )
                    OR (
                        decision_type IN ('no_match', 'manual_unassign')
                        AND selected_session_id IS NULL
                    )
                )
            SQL);
        }
    }

    protected function dropDecisionConsistencyRules(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('DROP TRIGGER IF EXISTS trg_asad_subject_identity_insert');
            DB::statement('DROP TRIGGER IF EXISTS trg_asad_subject_identity_update');
            DB::statement('DROP TRIGGER IF EXISTS trg_asad_selected_session_insert');
            DB::statement('DROP TRIGGER IF EXISTS trg_asad_selected_session_update');
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE asset_session_assignment_decisions DROP CONSTRAINT chk_asad_subject_identity');
            DB::statement('ALTER TABLE asset_session_assignment_decisions DROP CONSTRAINT chk_asad_selected_session');
            return;
        }

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE asset_session_assignment_decisions DROP CHECK chk_asad_subject_identity');
            DB::statement('ALTER TABLE asset_session_assignment_decisions DROP CHECK chk_asad_selected_session');
        }
    }
};

