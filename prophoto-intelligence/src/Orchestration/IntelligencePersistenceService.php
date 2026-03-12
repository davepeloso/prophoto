<?php

namespace ProPhoto\Intelligence\Orchestration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use JsonException;
use ProPhoto\Contracts\DTOs\EmbeddingResult;
use InvalidArgumentException;
use ProPhoto\Contracts\DTOs\GeneratorResult;
use ProPhoto\Contracts\DTOs\LabelResult;

class IntelligencePersistenceService
{
    public function persist(GeneratorResult $result): void
    {
        $hasLabels = $result->labels !== [];
        $hasEmbeddings = $result->embeddings !== [];

        if (! $hasLabels && ! $hasEmbeddings) {
            throw new InvalidArgumentException('Persistence received no outputs to persist.');
        }

        if ($hasLabels) {
            $this->persistLabels($result);
        }

        if ($hasEmbeddings) {
            $this->persistEmbeddings($result);
        }
    }

    public function persistLabels(GeneratorResult $result): void
    {
        if ($result->labels === []) {
            throw new InvalidArgumentException('Persistence received an empty label payload for a labels-required run.');
        }

        $runId = (int) $result->runContext->runId;
        $assetId = $result->runContext->assetId->toInt();
        $now = now()->toDateTimeString();
        $rows = [];

        foreach ($result->labels as $labelResult) {
            if (! $labelResult instanceof LabelResult) {
                throw new InvalidArgumentException('Generator labels must contain LabelResult DTOs.');
            }

            if ((string) $labelResult->runId !== (string) $result->runContext->runId) {
                throw new InvalidArgumentException('LabelResult run ID does not match GeneratorResult run context.');
            }

            if ($labelResult->assetId->toString() !== $result->runContext->assetId->toString()) {
                throw new InvalidArgumentException('LabelResult asset ID does not match GeneratorResult run context.');
            }

            $rows[] = [
                'asset_id' => $assetId,
                'run_id' => $runId,
                'label' => $labelResult->label,
                'confidence' => $labelResult->confidence,
                'created_at' => $labelResult->createdAt ?? $now,
            ];
        }

        // insertOrIgnore ensures run-scoped idempotency during retries.
        DB::table('asset_labels')->insertOrIgnore($rows);
    }

    public function persistEmbeddings(GeneratorResult $result): void
    {
        if ($result->embeddings === []) {
            throw new InvalidArgumentException('Persistence received an empty embedding payload for an embeddings-required run.');
        }
        if (count($result->embeddings) !== 1) {
            throw new InvalidArgumentException('Embedding persistence currently requires exactly one embedding per run.');
        }

        $runId = (int) $result->runContext->runId;
        $assetId = $result->runContext->assetId->toInt();
        $now = now()->toDateTimeString();
        $rows = [];

        foreach ($result->embeddings as $embeddingResult) {
            if (! $embeddingResult instanceof EmbeddingResult) {
                throw new InvalidArgumentException('Generator embeddings must contain EmbeddingResult DTOs.');
            }

            if ((string) $embeddingResult->runId !== (string) $result->runContext->runId) {
                throw new InvalidArgumentException('EmbeddingResult run ID does not match GeneratorResult run context.');
            }

            if ($embeddingResult->assetId->toString() !== $result->runContext->assetId->toString()) {
                throw new InvalidArgumentException('EmbeddingResult asset ID does not match GeneratorResult run context.');
            }

            if ($embeddingResult->vectorDimensions <= 0) {
                throw new InvalidArgumentException('EmbeddingResult vector dimensions must be greater than zero.');
            }

            if ($embeddingResult->vectorDimensions !== count($embeddingResult->embeddingVector)) {
                throw new InvalidArgumentException('EmbeddingResult vector dimensions do not match vector payload size.');
            }
            foreach ($embeddingResult->embeddingVector as $index => $value) {
                if (! is_int($value) && ! is_float($value)) {
                    throw new InvalidArgumentException(
                        sprintf('EmbeddingResult vector value at index %d must be numeric.', $index)
                    );
                }

                if (is_float($value) && ! is_finite($value)) {
                    throw new InvalidArgumentException(
                        sprintf('EmbeddingResult vector value at index %d must be finite.', $index)
                    );
                }
            }

            try {
                $encodedVector = json_encode($embeddingResult->embeddingVector, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new InvalidArgumentException('EmbeddingResult vector cannot be JSON encoded.', previous: $exception);
            }

            $rows[] = [
                'asset_id' => $assetId,
                'run_id' => $runId,
                'embedding_vector' => $encodedVector,
                'vector_dimensions' => $embeddingResult->vectorDimensions,
                'created_at' => $embeddingResult->createdAt ?? $now,
            ];
        }

        // insertOrIgnore ensures run-scoped idempotency during retries.
        $inserted = DB::table('asset_embeddings')->insertOrIgnore($rows);

        if ($inserted === 0) {
            Log::notice('Embedding persistence ignored duplicate row during retry-safe insert.', [
                'run_id' => $runId,
                'asset_id' => $assetId,
                'row_count' => count($rows),
            ]);
        }
    }
}
