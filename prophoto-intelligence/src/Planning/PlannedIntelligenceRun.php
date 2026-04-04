<?php

namespace ProPhoto\Intelligence\Planning;

use InvalidArgumentException;
use ProPhoto\Contracts\DTOs\AssetId;
use ProPhoto\Contracts\Enums\RunScope;

class PlannedIntelligenceRun
{
    public const DECISION_PLANNED = 'planned';
    public const DECISION_SKIPPED = 'skipped';

    /**
     * @param list<string> $required_outputs
     */
    public function __construct(
        public readonly AssetId $asset_id,
        public readonly string $generator_type,
        public readonly string $generator_version,
        public readonly string $model_name,
        public readonly string $model_version,
        public readonly string $configuration_hash,
        public readonly RunScope $run_scope,
        public readonly string $trigger_source,
        public readonly array $required_outputs,
        public readonly string $decision,
        public readonly ?string $skip_reason = null
    ) {
        if ($this->required_outputs === []) {
            throw new InvalidArgumentException('PlannedIntelligenceRun requires at least one required_output.');
        }

        if (! in_array($this->decision, [self::DECISION_PLANNED, self::DECISION_SKIPPED], true)) {
            throw new InvalidArgumentException('PlannedIntelligenceRun decision must be planned or skipped.');
        }

        if ($this->decision === self::DECISION_SKIPPED && $this->skip_reason === null) {
            throw new InvalidArgumentException('Skipped run decisions require a skip_reason.');
        }

        if ($this->decision === self::DECISION_PLANNED && $this->skip_reason !== null) {
            throw new InvalidArgumentException('Planned run decisions cannot include a skip_reason.');
        }
    }

    /**
     * @param list<string> $requiredOutputs
     */
    public static function planned(
        AssetId $assetId,
        string $generatorType,
        string $generatorVersion,
        string $modelName,
        string $modelVersion,
        string $configurationHash,
        RunScope $runScope,
        string $triggerSource,
        array $requiredOutputs
    ): self {
        return new self(
            asset_id: $assetId,
            generator_type: $generatorType,
            generator_version: $generatorVersion,
            model_name: $modelName,
            model_version: $modelVersion,
            configuration_hash: $configurationHash,
            run_scope: $runScope,
            trigger_source: $triggerSource,
            required_outputs: $requiredOutputs,
            decision: self::DECISION_PLANNED,
            skip_reason: null
        );
    }

    /**
     * @param list<string> $requiredOutputs
     */
    public static function skipped(
        AssetId $assetId,
        string $generatorType,
        string $generatorVersion,
        string $modelName,
        string $modelVersion,
        string $configurationHash,
        RunScope $runScope,
        string $triggerSource,
        array $requiredOutputs,
        PlannerDecisionReason $reason
    ): self {
        return new self(
            asset_id: $assetId,
            generator_type: $generatorType,
            generator_version: $generatorVersion,
            model_name: $modelName,
            model_version: $modelVersion,
            configuration_hash: $configurationHash,
            run_scope: $runScope,
            trigger_source: $triggerSource,
            required_outputs: $requiredOutputs,
            decision: self::DECISION_SKIPPED,
            skip_reason: $reason->value
        );
    }
}
