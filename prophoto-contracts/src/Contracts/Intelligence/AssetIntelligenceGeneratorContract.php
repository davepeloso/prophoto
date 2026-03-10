<?php

namespace ProPhoto\Contracts\Contracts\Intelligence;

use ProPhoto\Contracts\DTOs\GeneratorResult;
use ProPhoto\Contracts\DTOs\IntelligenceRunContext;

interface AssetIntelligenceGeneratorContract
{
    /**
     * Stable identifier for this generator implementation.
     */
    public function generatorType(): string;

    /**
     * Version of generator logic/pipeline independent of model version.
     */
    public function generatorVersion(): string;

    /**
     * Execute one intelligence run and return run-scoped derived outputs.
     *
     * @param array<string, mixed> $canonicalMetadata
     */
    public function generate(IntelligenceRunContext $runContext, array $canonicalMetadata = []): GeneratorResult;
}
