<?php

namespace ProPhoto\Assets\Tests\Feature;

use ProPhoto\Assets\Tests\TestCase;
use ProPhoto\Contracts\Events\Asset\AssetCreated;
use ProPhoto\Contracts\Events\Asset\AssetDerivativesGenerated;
use ProPhoto\Contracts\Events\Asset\AssetMetadataExtracted;
use ProPhoto\Contracts\Events\Asset\AssetMetadataNormalized;
use ProPhoto\Contracts\Events\Asset\AssetReadyV1;
use ProPhoto\Contracts\Events\Asset\AssetStored;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

class AssetEventContractShapeTest extends TestCase
{
    public function test_asset_event_constructor_signatures_are_stable(): void
    {
        $this->assertEventSignature(AssetCreated::class, [
            ['assetId', 'ProPhoto\Contracts\DTOs\AssetId'],
            ['studioId', 'int|string'],
            ['type', 'ProPhoto\Contracts\Enums\AssetType'],
            ['logicalPath', 'string'],
            ['occurredAt', 'string'],
        ]);

        $this->assertEventSignature(AssetStored::class, [
            ['assetId', 'ProPhoto\Contracts\DTOs\AssetId'],
            ['storageDriver', 'string'],
            ['storageKeyOriginal', 'string'],
            ['bytes', 'int'],
            ['checksumSha256', 'string'],
            ['occurredAt', 'string'],
        ]);

        $this->assertEventSignature(AssetMetadataExtracted::class, [
            ['assetId', 'ProPhoto\Contracts\DTOs\AssetId'],
            ['source', 'string'],
            ['extractedAt', 'string'],
            ['occurredAt', 'string'],
        ]);

        $this->assertEventSignature(AssetMetadataNormalized::class, [
            ['assetId', 'ProPhoto\Contracts\DTOs\AssetId'],
            ['schemaVersion', 'string'],
            ['normalizedAt', 'string'],
            ['occurredAt', 'string'],
        ]);

        $this->assertEventSignature(AssetDerivativesGenerated::class, [
            ['assetId', 'ProPhoto\Contracts\DTOs\AssetId'],
            ['derivativeTypes', 'array'],
            ['occurredAt', 'string'],
        ]);

        $this->assertEventSignature(AssetReadyV1::class, [
            ['assetId', 'ProPhoto\Contracts\DTOs\AssetId'],
            ['studioId', 'int|string'],
            ['status', 'string'],
            ['hasOriginal', 'bool'],
            ['hasNormalizedMetadata', 'bool'],
            ['hasDerivatives', 'bool'],
            ['occurredAt', 'string'],
        ]);
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

