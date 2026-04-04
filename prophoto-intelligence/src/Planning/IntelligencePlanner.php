<?php

namespace ProPhoto\Intelligence\Planning;

use JsonException;
use ProPhoto\Contracts\DTOs\AssetId;
use ProPhoto\Contracts\Enums\RunScope;
use ProPhoto\Contracts\Enums\RunStatus;

class IntelligencePlanner
{
    /**
     * @param array<string, mixed> $canonicalMetadata
     * @param list<GeneratorDescriptor> $generatorDescriptors
     * @param array<string, mixed> $intelligenceConfig
     * @param list<array<string, mixed>|object> $existingRunSummaries
     * @return list<PlannedIntelligenceRun>
     */
    public function plan(
        AssetId $assetId,
        array $canonicalMetadata,
        array $generatorDescriptors,
        array $intelligenceConfig = [],
        array $existingRunSummaries = [],
        string $triggerSource = 'asset_ready',
        RunScope $runScope = RunScope::SINGLE_ASSET
    ): array {
        $globalEnabled = (bool) ($intelligenceConfig['enabled'] ?? true);
        $isReadyForIntelligence = $this->isReadyForIntelligence($canonicalMetadata);
        $mediaKind = $this->resolveMediaKind($canonicalMetadata);
        $plans = [];

        foreach ($generatorDescriptors as $descriptor) {
            if (! $descriptor instanceof GeneratorDescriptor) {
                continue;
            }

            $generatorConfig = $this->generatorConfig($intelligenceConfig, $descriptor->generator_type);
            $requiredOutputs = $descriptor->produces_outputs;
            $effectiveGeneratorVersion = (string) ($generatorConfig['generator_version'] ?? $descriptor->generator_version);
            $modelName = (string) ($generatorConfig['model_name'] ?? $descriptor->default_model_name);
            $modelVersion = (string) ($generatorConfig['model_version'] ?? $descriptor->default_model_version);
            $configurationHash = $this->configurationHash(
                generatorType: $descriptor->generator_type,
                generatorVersion: $effectiveGeneratorVersion,
                modelName: $modelName,
                modelVersion: $modelVersion,
                requiredOutputs: $requiredOutputs,
                options: is_array($generatorConfig['options'] ?? null) ? $generatorConfig['options'] : []
            );

            if (! $globalEnabled || ! (bool) ($generatorConfig['enabled'] ?? true)) {
                $plans[] = PlannedIntelligenceRun::skipped(
                    assetId: $assetId,
                    generatorType: $descriptor->generator_type,
                    generatorVersion: $effectiveGeneratorVersion,
                    modelName: $modelName,
                    modelVersion: $modelVersion,
                    configurationHash: $configurationHash,
                    runScope: $runScope,
                    triggerSource: $triggerSource,
                    requiredOutputs: $requiredOutputs,
                    reason: PlannerDecisionReason::DISABLED_BY_CONFIG
                );
                continue;
            }

            if (! $isReadyForIntelligence) {
                $plans[] = PlannedIntelligenceRun::skipped(
                    assetId: $assetId,
                    generatorType: $descriptor->generator_type,
                    generatorVersion: $effectiveGeneratorVersion,
                    modelName: $modelName,
                    modelVersion: $modelVersion,
                    configurationHash: $configurationHash,
                    runScope: $runScope,
                    triggerSource: $triggerSource,
                    requiredOutputs: $requiredOutputs,
                    reason: PlannerDecisionReason::ASSET_NOT_READY
                );
                continue;
            }

            if ($mediaKind === null) {
                $plans[] = PlannedIntelligenceRun::skipped(
                    assetId: $assetId,
                    generatorType: $descriptor->generator_type,
                    generatorVersion: $effectiveGeneratorVersion,
                    modelName: $modelName,
                    modelVersion: $modelVersion,
                    configurationHash: $configurationHash,
                    runScope: $runScope,
                    triggerSource: $triggerSource,
                    requiredOutputs: $requiredOutputs,
                    reason: PlannerDecisionReason::UNSUPPORTED_MEDIA_KIND
                );
                continue;
            }

            if (! in_array($mediaKind, $descriptor->supported_media_kinds, true)) {
                $plans[] = PlannedIntelligenceRun::skipped(
                    assetId: $assetId,
                    generatorType: $descriptor->generator_type,
                    generatorVersion: $effectiveGeneratorVersion,
                    modelName: $modelName,
                    modelVersion: $modelVersion,
                    configurationHash: $configurationHash,
                    runScope: $runScope,
                    triggerSource: $triggerSource,
                    requiredOutputs: $requiredOutputs,
                    reason: PlannerDecisionReason::UNSUPPORTED_MEDIA_KIND
                );
                continue;
            }

            if ($this->hasActiveRun(
                existingRunSummaries: $existingRunSummaries,
                assetId: $assetId,
                generatorType: $descriptor->generator_type,
                generatorVersion: $effectiveGeneratorVersion,
                modelName: $modelName,
                modelVersion: $modelVersion
            )) {
                $plans[] = PlannedIntelligenceRun::skipped(
                    assetId: $assetId,
                    generatorType: $descriptor->generator_type,
                    generatorVersion: $effectiveGeneratorVersion,
                    modelName: $modelName,
                    modelVersion: $modelVersion,
                    configurationHash: $configurationHash,
                    runScope: $runScope,
                    triggerSource: $triggerSource,
                    requiredOutputs: $requiredOutputs,
                    reason: PlannerDecisionReason::ACTIVE_RUN_EXISTS
                );
                continue;
            }

            if ($this->hasMatchingCompletedRun(
                existingRunSummaries: $existingRunSummaries,
                assetId: $assetId,
                generatorType: $descriptor->generator_type,
                configurationHash: $configurationHash
            )) {
                $plans[] = PlannedIntelligenceRun::skipped(
                    assetId: $assetId,
                    generatorType: $descriptor->generator_type,
                    generatorVersion: $effectiveGeneratorVersion,
                    modelName: $modelName,
                    modelVersion: $modelVersion,
                    configurationHash: $configurationHash,
                    runScope: $runScope,
                    triggerSource: $triggerSource,
                    requiredOutputs: $requiredOutputs,
                    reason: PlannerDecisionReason::MATCHING_COMPLETED_RUN_EXISTS
                );
                continue;
            }

            $plans[] = PlannedIntelligenceRun::planned(
                assetId: $assetId,
                generatorType: $descriptor->generator_type,
                generatorVersion: $effectiveGeneratorVersion,
                modelName: $modelName,
                modelVersion: $modelVersion,
                configurationHash: $configurationHash,
                runScope: $runScope,
                triggerSource: $triggerSource,
                requiredOutputs: $requiredOutputs
            );
        }

        return $plans;
    }

    /**
     * @param array<string, mixed> $intelligenceConfig
     * @return array<string, mixed>
     */
    protected function generatorConfig(array $intelligenceConfig, string $generatorType): array
    {
        $generators = $intelligenceConfig['generators'] ?? [];
        if (! is_array($generators)) {
            return [];
        }

        $config = $generators[$generatorType] ?? [];

        return is_array($config) ? $config : [];
    }

    /**
     * @param array<string, mixed> $canonicalMetadata
     */
    protected function isReadyForIntelligence(array $canonicalMetadata): bool
    {
        $flag = $canonicalMetadata['is_ready_for_intelligence'] ?? null;
        if (is_bool($flag)) {
            return $flag;
        }
        if (is_int($flag)) {
            return $flag === 1;
        }
        if (is_string($flag)) {
            $parsed = filter_var($flag, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        // v1 default assumes caller already filtered for ready-enough assets.
        return true;
    }

    /**
     * @param array<string, mixed> $canonicalMetadata
     */
    protected function resolveMediaKind(array $canonicalMetadata): ?string
    {
        $explicitMediaKind = $canonicalMetadata['media_kind'] ?? null;
        if (is_string($explicitMediaKind) && $explicitMediaKind !== '') {
            return strtolower($explicitMediaKind);
        }

        $mimeType = $canonicalMetadata['mime_type'] ?? null;
        if (! is_string($mimeType) || $mimeType === '') {
            return null;
        }

        $mimeType = strtolower($mimeType);
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }
        if ($mimeType === 'application/pdf') {
            return 'pdf';
        }
        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>|object> $existingRunSummaries
     */
    protected function hasActiveRun(
        array $existingRunSummaries,
        AssetId $assetId,
        string $generatorType,
        string $generatorVersion,
        string $modelName,
        string $modelVersion
    ): bool {
        foreach ($existingRunSummaries as $summary) {
            if (! $this->summaryMatchesAsset($summary, $assetId)) {
                continue;
            }

            $status = $this->summaryValue($summary, 'run_status') ?? $this->summaryValue($summary, 'status');
            if (! is_string($status)) {
                continue;
            }
            if (! in_array($status, [RunStatus::PENDING->value, RunStatus::RUNNING->value], true)) {
                continue;
            }
            if ($this->summaryValue($summary, 'generator_type') !== $generatorType) {
                continue;
            }
            if ((string) $this->summaryValue($summary, 'generator_version') !== $generatorVersion) {
                continue;
            }
            if ((string) $this->summaryValue($summary, 'model_name') !== $modelName) {
                continue;
            }
            if ((string) $this->summaryValue($summary, 'model_version') !== $modelVersion) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>|object> $existingRunSummaries
     */
    protected function hasMatchingCompletedRun(
        array $existingRunSummaries,
        AssetId $assetId,
        string $generatorType,
        string $configurationHash
    ): bool {
        foreach ($existingRunSummaries as $summary) {
            if (! $this->summaryMatchesAsset($summary, $assetId)) {
                continue;
            }

            $status = $this->summaryValue($summary, 'run_status') ?? $this->summaryValue($summary, 'status');
            if ($status !== RunStatus::COMPLETED->value) {
                continue;
            }
            if ($this->summaryValue($summary, 'generator_type') !== $generatorType) {
                continue;
            }
            if ((string) $this->summaryValue($summary, 'configuration_hash') !== $configurationHash) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * @param array<string, mixed>|object $summary
     */
    protected function summaryMatchesAsset(array|object $summary, AssetId $assetId): bool
    {
        $summaryAssetId = $this->summaryValue($summary, 'asset_id');
        if ($summaryAssetId === null) {
            return false;
        }

        return (string) $summaryAssetId === $assetId->toString();
    }

    /**
     * @param array<string, mixed>|object $summary
     */
    protected function summaryValue(array|object $summary, string $key): mixed
    {
        if (is_array($summary)) {
            return $summary[$key] ?? null;
        }
        if (is_object($summary) && property_exists($summary, $key)) {
            return $summary->{$key};
        }

        return null;
    }

    /**
     * @param list<string> $requiredOutputs
     * @param array<string, mixed> $options
     */
    protected function configurationHash(
        string $generatorType,
        string $generatorVersion,
        string $modelName,
        string $modelVersion,
        array $requiredOutputs,
        array $options
    ): string {
        $payload = [
            'generator_type' => $generatorType,
            'generator_version' => $generatorVersion,
            'model_name' => $modelName,
            'model_version' => $modelVersion,
            'required_outputs' => array_values($requiredOutputs),
            'options' => $this->sortRecursive($options),
        ];

        try {
            return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        } catch (JsonException) {
            return hash('sha256', serialize($payload));
        }
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    protected function sortRecursive(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $isList = array_is_list($value);
        if ($isList) {
            return array_map(fn ($item) => $this->sortRecursive($item), $value);
        }

        $sorted = [];
        $keys = array_keys($value);
        sort($keys);

        foreach ($keys as $key) {
            $sorted[(string) $key] = $this->sortRecursive($value[$key]);
        }

        return $sorted;
    }
}
