<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ProPhoto\Contracts\Enums\SessionAssignmentMode;
use ProPhoto\Contracts\Enums\SessionAssociationLockState;
use ProPhoto\Contracts\Enums\SessionAssociationSubjectType;
use ProPhoto\Contracts\Enums\SessionMatchConfidenceTier;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_session_assignments', function (Blueprint $table) {
            $table->id();
            $table->enum('subject_type', $this->subjectTypeValues());
            $table->string('subject_id', 191);
            $table->string('ingest_item_id', 191)->nullable();
            $table->unsignedBigInteger('asset_id')->nullable();
            $table->unsignedBigInteger('session_id')->nullable();
            $table->enum('effective_state', ['assigned', 'unassigned']);
            $table->enum('assignment_mode', $this->assignmentModeValues());
            $table->enum('manual_lock_state', $this->lockStateValues());
            $table->unsignedBigInteger('source_decision_id');
            $table->enum('confidence_tier', $this->confidenceTierValues())->nullable();
            $table->decimal('confidence_score', 6, 5)->nullable();
            $table->string('reason_code', 64)->nullable();
            $table->timestamp('became_effective_at');
            $table->timestamp('superseded_at')->nullable();
            $table->unsignedBigInteger('superseded_by_assignment_id')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id'], 'idx_asa_subject');
            $table->index('asset_id', 'idx_asa_asset_id');
            $table->index('session_id', 'idx_asa_session_id');
            $table->index('source_decision_id', 'idx_asa_source_decision_id');
            $table->index('superseded_by_assignment_id', 'idx_asa_superseded_by_assignment_id');
            $table->index('became_effective_at', 'idx_asa_became_effective_at');

            $table->foreign('asset_id', 'fk_asa_asset_id')
                ->references('id')
                ->on('assets')
                ->nullOnDelete();

            $table->foreign('session_id', 'fk_asa_session_id')
                ->references('id')
                ->on('sessions')
                ->nullOnDelete();

            $table->foreign('source_decision_id', 'fk_asa_source_decision_id')
                ->references('id')
                ->on('asset_session_assignment_decisions')
                ->restrictOnDelete();

            $table->foreign('superseded_by_assignment_id', 'fk_asa_superseded_by_assignment_id')
                ->references('id')
                ->on('asset_session_assignments')
                ->nullOnDelete();
        });

        $this->applyAssignmentConsistencyRules();
        $this->createCurrentAssignmentConstraint();
    }

    public function down(): void
    {
        $this->dropCurrentAssignmentConstraint();
        $this->dropAssignmentConsistencyRules();
        Schema::dropIfExists('asset_session_assignments');
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
    protected function assignmentModeValues(): array
    {
        return array_map(
            static fn (SessionAssignmentMode $mode): string => $mode->value,
            SessionAssignmentMode::cases()
        );
    }

    /**
     * @return list<string>
     */
    protected function lockStateValues(): array
    {
        return array_map(
            static fn (SessionAssociationLockState $state): string => $state->value,
            SessionAssociationLockState::cases()
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

    protected function applyAssignmentConsistencyRules(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement(<<<'SQL'
                CREATE TRIGGER trg_asa_subject_identity_insert
                BEFORE INSERT ON asset_session_assignments
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
                CREATE TRIGGER trg_asa_subject_identity_update
                BEFORE UPDATE ON asset_session_assignments
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
                CREATE TRIGGER trg_asa_effective_state_insert
                BEFORE INSERT ON asset_session_assignments
                FOR EACH ROW
                BEGIN
                    SELECT CASE
                        WHEN NEW.effective_state = 'assigned' AND NEW.session_id IS NULL
                            THEN RAISE(ABORT, 'effective_state assigned requires session_id')
                        WHEN NEW.effective_state = 'unassigned' AND NEW.session_id IS NOT NULL
                            THEN RAISE(ABORT, 'effective_state unassigned requires null session_id')
                    END;
                END;
            SQL);

            DB::statement(<<<'SQL'
                CREATE TRIGGER trg_asa_effective_state_update
                BEFORE UPDATE ON asset_session_assignments
                FOR EACH ROW
                BEGIN
                    SELECT CASE
                        WHEN NEW.effective_state = 'assigned' AND NEW.session_id IS NULL
                            THEN RAISE(ABORT, 'effective_state assigned requires session_id')
                        WHEN NEW.effective_state = 'unassigned' AND NEW.session_id IS NOT NULL
                            THEN RAISE(ABORT, 'effective_state unassigned requires null session_id')
                    END;
                END;
            SQL);

            return;
        }

        if (in_array($driver, ['pgsql', 'mysql'], true)) {
            DB::statement(<<<'SQL'
                ALTER TABLE asset_session_assignments
                ADD CONSTRAINT chk_asa_subject_identity
                CHECK (
                    (subject_type = 'ingest_item' AND ingest_item_id IS NOT NULL)
                    OR (subject_type = 'asset' AND asset_id IS NOT NULL)
                )
            SQL);

            DB::statement(<<<'SQL'
                ALTER TABLE asset_session_assignments
                ADD CONSTRAINT chk_asa_effective_state
                CHECK (
                    (effective_state = 'assigned' AND session_id IS NOT NULL)
                    OR (effective_state = 'unassigned' AND session_id IS NULL)
                )
            SQL);
        }
    }

    protected function dropAssignmentConsistencyRules(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('DROP TRIGGER IF EXISTS trg_asa_subject_identity_insert');
            DB::statement('DROP TRIGGER IF EXISTS trg_asa_subject_identity_update');
            DB::statement('DROP TRIGGER IF EXISTS trg_asa_effective_state_insert');
            DB::statement('DROP TRIGGER IF EXISTS trg_asa_effective_state_update');
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE asset_session_assignments DROP CONSTRAINT chk_asa_subject_identity');
            DB::statement('ALTER TABLE asset_session_assignments DROP CONSTRAINT chk_asa_effective_state');
            return;
        }

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE asset_session_assignments DROP CHECK chk_asa_subject_identity');
            DB::statement('ALTER TABLE asset_session_assignments DROP CHECK chk_asa_effective_state');
        }
    }

    protected function createCurrentAssignmentConstraint(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        $indexName = 'uq_asa_current_subject';

        if (in_array($driver, ['sqlite', 'pgsql'], true)) {
            DB::statement(<<<SQL
                CREATE UNIQUE INDEX {$indexName}
                ON asset_session_assignments (subject_type, subject_id)
                WHERE superseded_at IS NULL
            SQL);
            return;
        }

        if ($driver === 'mysql') {
            DB::statement(<<<SQL
                CREATE UNIQUE INDEX {$indexName}
                ON asset_session_assignments (
                    (CASE
                        WHEN superseded_at IS NULL THEN CONCAT(subject_type, ':', subject_id)
                        ELSE NULL
                    END)
                )
            SQL);
        }
    }

    protected function dropCurrentAssignmentConstraint(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        $indexName = 'uq_asa_current_subject';

        if ($driver === 'pgsql') {
            DB::statement("DROP INDEX IF EXISTS {$indexName}");
            return;
        }

        if ($driver === 'sqlite') {
            DB::statement("DROP INDEX IF EXISTS {$indexName}");
            return;
        }

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE asset_session_assignments DROP INDEX {$indexName}");
        }
    }
};
