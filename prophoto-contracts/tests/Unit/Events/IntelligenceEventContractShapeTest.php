<?php

namespace ProPhoto\Contracts\Tests\Unit\Events;

use PHPUnit\Framework\TestCase;
use ProPhoto\Contracts\Events\Intelligence\AssetEmbeddingUpdated;
use ProPhoto\Contracts\Events\Intelligence\AssetIntelligenceGenerated;
use ProPhoto\Contracts\Events\Intelligence\AssetIntelligenceRunStarted;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

class IntelligenceEventContractShapeTest extends TestCase
{
    public function test_intelligence_event_constructor_signatures_are_stable(): void
    {
        $startedExpected = [
            ['assetId', 'ProPhoto\Contracts\DTOs\AssetId'],
            ['runId', 'int|string'],
            ['generatorType', 'string'],
            ['generatorVersion', 'string'],
            ['modelName', 'string'],
            ['modelVersion', 'string'],
            ['occurredAt', 'string'],
        ];

        $completionExpected = [
            ['assetId', 'ProPhoto\Contracts\DTOs\AssetId'],
            ['runId', 'int|string'],
            ['generatorType', 'string'],
            ['generatorVersion', 'string'],
            ['modelName', 'string'],
            ['modelVersion', 'string'],
            ['resultTypes', 'array'],
            ['occurredAt', 'string'],
        ];

        $this->assertEventSignature(AssetIntelligenceRunStarted::class, $startedExpected);
        $this->assertEventSignature(AssetIntelligenceGenerated::class, $completionExpected);
        $this->assertEventSignature(AssetEmbeddingUpdated::class, $completionExpected);
    }

    /**
     * @param list<array{0: string, 1: string}> $expectedSignature
     */
    protected function assertEventSignature(string $eventClass, array $expectedSignature): void
    {
        $reflection = new \ReflectionClass($eventClass);
        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor, "{$eventClass} constructor is required.");

        $actual = [];
        foreach ($constructor->getParameters() as $parameter) {
            $actual[] = [$parameter->getName(), $this->renderType($parameter->getType())];
        }

        $this->assertSame($expectedSignature, $actual, "{$eventClass} signature drifted.");
    }

    protected function renderType(?ReflectionType $type): string
    {
        if ($type === null) {
            return 'mixed';
        }

        if ($type instanceof ReflectionNamedType) {
            return $type->getName();
        }

        if ($type instanceof ReflectionUnionType) {
            $union = array_map(
                static fn (ReflectionNamedType $named): string => $named->getName(),
                $type->getTypes()
            );
            sort($union);

            return implode('|', $union);
        }

        return (string) $type;
    }
}
