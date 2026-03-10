<?php

namespace ProPhoto\Contracts\Tests\Feature;

use PHPUnit\Framework\TestCase;
use ProPhoto\Contracts\Contracts\Intelligence\AssetEmbeddingRepositoryContract;
use ProPhoto\Contracts\Contracts\Intelligence\AssetIntelligenceGeneratorContract;
use ProPhoto\Contracts\Contracts\Intelligence\AssetLabelRepositoryContract;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

class IntelligenceContractSignatureTest extends TestCase
{
    public function test_intelligence_generator_contract_signature_is_stable(): void
    {
        $this->assertMethodSignatures(AssetIntelligenceGeneratorContract::class, [
            ['generatorType', [], 'string'],
            ['generatorVersion', [], 'string'],
            ['generate', [
                ['runContext', 'ProPhoto\Contracts\DTOs\IntelligenceRunContext'],
                ['canonicalMetadata', 'array'],
            ], 'ProPhoto\Contracts\DTOs\GeneratorResult'],
        ]);
    }

    public function test_label_repository_contract_signature_is_stable(): void
    {
        $this->assertMethodSignatures(AssetLabelRepositoryContract::class, [
            ['findByAsset', [
                ['assetId', 'ProPhoto\Contracts\DTOs\AssetId'],
                ['generatorType', 'string'],
                ['modelName', 'string'],
                ['modelVersion', 'string'],
            ], 'array'],
            ['findByRun', [
                ['runId', 'int|string'],
            ], 'array'],
            ['findLatestForAsset', [
                ['assetId', 'ProPhoto\Contracts\DTOs\AssetId'],
                ['generatorType', 'string'],
                ['modelName', 'string'],
                ['modelVersion', 'string'],
            ], 'array'],
        ]);
    }

    public function test_embedding_repository_contract_signature_is_stable(): void
    {
        $this->assertMethodSignatures(AssetEmbeddingRepositoryContract::class, [
            ['findByAsset', [
                ['assetId', 'ProPhoto\Contracts\DTOs\AssetId'],
                ['generatorType', 'string'],
                ['modelName', 'string'],
                ['modelVersion', 'string'],
            ], 'ProPhoto\Contracts\DTOs\EmbeddingResult'],
            ['findByRun', [
                ['runId', 'int|string'],
            ], 'ProPhoto\Contracts\DTOs\EmbeddingResult'],
            ['findLatestForAsset', [
                ['assetId', 'ProPhoto\Contracts\DTOs\AssetId'],
                ['generatorType', 'string'],
                ['modelName', 'string'],
                ['modelVersion', 'string'],
            ], 'ProPhoto\Contracts\DTOs\EmbeddingResult'],
        ]);
    }

    /**
     * @param list<array{0: string, 1: list<array{0: string, 1: string}>, 2: string}> $expectedMethods
     */
    protected function assertMethodSignatures(string $interfaceClass, array $expectedMethods): void
    {
        $reflection = new \ReflectionClass($interfaceClass);
        $this->assertTrue($reflection->isInterface(), "{$interfaceClass} must be an interface.");

        $actual = [];
        foreach ($reflection->getMethods() as $method) {
            $parameters = [];
            foreach ($method->getParameters() as $parameter) {
                $parameters[] = [$parameter->getName(), $this->renderType($parameter->getType())];
            }

            $actual[] = [$method->getName(), $parameters, $this->renderType($method->getReturnType())];
        }

        $this->assertSame($expectedMethods, $actual, "{$interfaceClass} signature drifted.");
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
