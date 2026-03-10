<?php

namespace ProPhoto\Contracts\DTOs;

readonly class GeneratorResult
{
    /**
     * @param list<LabelResult> $labels
     * @param list<EmbeddingResult> $embeddings
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public IntelligenceRunContext $runContext,
        public array $labels = [],
        public array $embeddings = [],
        public array $meta = []
    ) {}

    /**
     * @return list<string>
     */
    public function resultTypes(): array
    {
        $types = [];

        if ($this->labels !== []) {
            $types[] = 'labels';
        }

        if ($this->embeddings !== []) {
            $types[] = 'embeddings';
        }

        return $types;
    }

    public function hasAnyResults(): bool
    {
        return $this->labels !== [] || $this->embeddings !== [];
    }
}
