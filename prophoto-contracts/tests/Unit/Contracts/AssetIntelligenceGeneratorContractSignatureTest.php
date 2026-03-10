<?php

namespace ProPhoto\Contracts\Tests\Unit\Contracts;

use PHPUnit\Framework\TestCase;
use ProPhoto\Contracts\Contracts\Intelligence\AssetIntelligenceGeneratorContract;
use ProPhoto\Contracts\DTOs\GeneratorResult;
use ProPhoto\Contracts\DTOs\IntelligenceRunContext;
use ReflectionNamedType;
use ReflectionUnionType;

class AssetIntelligenceGeneratorContractSignatureTest extends TestCase
{
    public function test_generate_method_signature_uses_generator_result_and_expected_parameters(): void
    {
        $reflection = new \ReflectionClass(AssetIntelligenceGeneratorContract::class);
        $method = $reflection->getMethod('generate');

        $this->assertSame(2, $method->getNumberOfParameters());

        $firstParameter = $method->getParameters()[0];
        $this->assertSame('runContext', $firstParameter->getName());
        $this->assertSame(IntelligenceRunContext::class, $this->renderType($firstParameter->getType()));

        $secondParameter = $method->getParameters()[1];
        $this->assertSame('canonicalMetadata', $secondParameter->getName());
        $this->assertSame('array', $this->renderType($secondParameter->getType()));

        $this->assertSame(GeneratorResult::class, $this->renderType($method->getReturnType()));
    }

    protected function renderType(\ReflectionType|null $type): string
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
