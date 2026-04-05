<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    skipIfDisabled('migrations');
});

test('asset session association tables exist', function () {
    expect(Schema::hasTable('asset_session_assignment_decisions'))->toBeTrue();
    expect(Schema::hasTable('asset_session_assignments'))->toBeTrue();
});

test('asset session assignment decisions table has required columns', function () {
    expect(Schema::hasColumns('asset_session_assignment_decisions', [
        'id',
        'decision_type',
        'subject_type',
        'subject_id',
        'ingest_item_id',
        'asset_id',
        'selected_session_id',
        'confidence_tier',
        'confidence_score',
        'algorithm_version',
        'trigger_source',
        'evidence_payload',
        'ranked_candidates_payload',
        'calendar_context_state',
        'manual_override_reason_code',
        'manual_override_note',
        'lock_effect',
        'supersedes_decision_id',
        'idempotency_key',
        'actor_type',
        'actor_id',
        'created_at',
    ]))->toBeTrue();
});

test('asset session assignments table has required columns', function () {
    expect(Schema::hasColumns('asset_session_assignments', [
        'id',
        'subject_type',
        'subject_id',
        'ingest_item_id',
        'asset_id',
        'session_id',
        'effective_state',
        'assignment_mode',
        'manual_lock_state',
        'source_decision_id',
        'confidence_tier',
        'confidence_score',
        'reason_code',
        'became_effective_at',
        'superseded_at',
        'superseded_by_assignment_id',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

test('one current effective assignment per subject is enforced', function () {
    $now = now()->toDateTimeString();
    $firstDecisionId = insertDecision(
        decisionType: 'no_match',
        subjectType: 'ingest_item',
        subjectId: 'ing_100',
        ingestItemId: 'ing_100',
        selectedSessionId: null,
        createdAt: $now
    );

    DB::table('asset_session_assignments')->insert([
        'subject_type' => 'ingest_item',
        'subject_id' => 'ing_100',
        'ingest_item_id' => 'ing_100',
        'asset_id' => null,
        'session_id' => null,
        'effective_state' => 'unassigned',
        'assignment_mode' => 'auto',
        'manual_lock_state' => 'none',
        'source_decision_id' => $firstDecisionId,
        'confidence_tier' => 'low',
        'confidence_score' => 0.10000,
        'reason_code' => 'no_match',
        'became_effective_at' => $now,
        'superseded_at' => null,
        'superseded_by_assignment_id' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $secondDecisionId = insertDecision(
        decisionType: 'no_match',
        subjectType: 'ingest_item',
        subjectId: 'ing_100',
        ingestItemId: 'ing_100',
        selectedSessionId: null,
        createdAt: $now
    );

    expect(fn () => DB::table('asset_session_assignments')->insert([
        'subject_type' => 'ingest_item',
        'subject_id' => 'ing_100',
        'ingest_item_id' => 'ing_100',
        'asset_id' => null,
        'session_id' => null,
        'effective_state' => 'unassigned',
        'assignment_mode' => 'auto',
        'manual_lock_state' => 'none',
        'source_decision_id' => $secondDecisionId,
        'confidence_tier' => 'low',
        'confidence_score' => 0.20000,
        'reason_code' => 'no_match_retry',
        'became_effective_at' => $now,
        'superseded_at' => null,
        'superseded_by_assignment_id' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]))->toThrow(QueryException::class);
});

test('decision subject identity alignment is enforced at database level', function () {
    expect(fn () => insertDecision(
        decisionType: 'no_match',
        subjectType: 'ingest_item',
        subjectId: 'ing_200',
        ingestItemId: null,
        selectedSessionId: null,
        createdAt: now()->toDateTimeString()
    ))->toThrow(QueryException::class);
});

test('assignment subject identity alignment is enforced at database level', function () {
    $now = now()->toDateTimeString();
    $decisionId = insertDecision(
        decisionType: 'manual_unassign',
        subjectType: 'asset',
        subjectId: 'asset_300',
        ingestItemId: null,
        selectedSessionId: null,
        createdAt: $now,
        assetId: 300
    );

    expect(fn () => DB::table('asset_session_assignments')->insert([
        'subject_type' => 'asset',
        'subject_id' => 'asset_300',
        'ingest_item_id' => null,
        'asset_id' => null,
        'session_id' => null,
        'effective_state' => 'unassigned',
        'assignment_mode' => 'manual',
        'manual_lock_state' => 'manual_unassigned_lock',
        'source_decision_id' => $decisionId,
        'confidence_tier' => null,
        'confidence_score' => null,
        'reason_code' => 'manual_clear',
        'became_effective_at' => $now,
        'superseded_at' => null,
        'superseded_by_assignment_id' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]))->toThrow(QueryException::class);
});

test('decision history table is structurally append only', function () {
    expect(Schema::hasColumn('asset_session_assignment_decisions', 'created_at'))->toBeTrue();
    expect(Schema::hasColumn('asset_session_assignment_decisions', 'updated_at'))->toBeFalse();
    expect(Schema::hasColumn('asset_session_assignment_decisions', 'deleted_at'))->toBeFalse();
    expect(Schema::hasColumn('asset_session_assignment_decisions', 'supersedes_decision_id'))->toBeTrue();
    expect(Schema::hasColumn('asset_session_assignment_decisions', 'idempotency_key'))->toBeTrue();
});

test('foreign key direction points to upstream session and asset tables plus local history tables', function () {
    $decisionFks = collect(DB::select("PRAGMA foreign_key_list('asset_session_assignment_decisions')"));
    expect($decisionFks->contains(fn (object $fk): bool => $fk->from === 'asset_id' && $fk->table === 'assets'))->toBeTrue();
    expect($decisionFks->contains(fn (object $fk): bool => $fk->from === 'selected_session_id' && $fk->table === 'sessions'))->toBeTrue();
    expect($decisionFks->contains(fn (object $fk): bool => $fk->from === 'supersedes_decision_id' && $fk->table === 'asset_session_assignment_decisions'))->toBeTrue();

    $assignmentFks = collect(DB::select("PRAGMA foreign_key_list('asset_session_assignments')"));
    expect($assignmentFks->contains(fn (object $fk): bool => $fk->from === 'asset_id' && $fk->table === 'assets'))->toBeTrue();
    expect($assignmentFks->contains(fn (object $fk): bool => $fk->from === 'session_id' && $fk->table === 'sessions'))->toBeTrue();
    expect($assignmentFks->contains(fn (object $fk): bool => $fk->from === 'source_decision_id' && $fk->table === 'asset_session_assignment_decisions'))->toBeTrue();
    expect($assignmentFks->contains(fn (object $fk): bool => $fk->from === 'superseded_by_assignment_id' && $fk->table === 'asset_session_assignments'))->toBeTrue();
});

test('ingest migrations do not mutate or create canonical asset ownership tables', function () {
    $migrationFiles = glob(__DIR__ . '/../../database/migrations/*.php');

    foreach ($migrationFiles as $migrationFile) {
        $source = file_get_contents($migrationFile);
        expect($source)->not->toContain("Schema::table('assets'");
        expect($source)->not->toContain('Schema::table("assets"');
        expect($source)->not->toContain("Schema::create('assets'");
        expect($source)->not->toContain('Schema::create("assets"');
    }
});

function insertDecision(
    string $decisionType,
    string $subjectType,
    string $subjectId,
    ?string $ingestItemId,
    int|string|null $selectedSessionId,
    string $createdAt,
    int|string|null $assetId = null
): int {
    return (int) DB::table('asset_session_assignment_decisions')->insertGetId([
        'decision_type' => $decisionType,
        'subject_type' => $subjectType,
        'subject_id' => $subjectId,
        'ingest_item_id' => $ingestItemId,
        'asset_id' => $assetId,
        'selected_session_id' => $selectedSessionId,
        'confidence_tier' => 'low',
        'confidence_score' => 0.10000,
        'algorithm_version' => 'v1',
        'trigger_source' => 'ingest_batch',
        'evidence_payload' => json_encode(['signals' => ['time_window' => true]], JSON_THROW_ON_ERROR),
        'ranked_candidates_payload' => json_encode([], JSON_THROW_ON_ERROR),
        'calendar_context_state' => 'normal',
        'manual_override_reason_code' => null,
        'manual_override_note' => null,
        'lock_effect' => 'none',
        'supersedes_decision_id' => null,
        'idempotency_key' => null,
        'actor_type' => 'system',
        'actor_id' => null,
        'created_at' => $createdAt,
    ]);
}
