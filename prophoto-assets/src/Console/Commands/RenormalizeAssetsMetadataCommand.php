<?php

namespace ProPhoto\Assets\Console\Commands;

use Illuminate\Console\Command;
use ProPhoto\Assets\Models\Asset;
use ProPhoto\Assets\Models\AssetMetadataRaw;
use ProPhoto\Contracts\Contracts\Metadata\AssetMetadataNormalizerContract;
use ProPhoto\Contracts\Contracts\Metadata\AssetMetadataRepositoryContract;
use ProPhoto\Contracts\DTOs\AssetId;
use ProPhoto\Contracts\DTOs\MetadataProvenance;
use ProPhoto\Contracts\DTOs\RawMetadataBundle;

class RenormalizeAssetsMetadataCommand extends Command
{
    protected $signature = 'prophoto-assets:renormalize
        {--asset-id= : Only re-normalize one asset}
        {--limit=0 : Max assets to process (0 = all)}
        {--dry-run : Preview actions without writes}';

    protected $description = 'Rebuild normalized metadata from latest raw metadata records.';

    public function handle(
        AssetMetadataNormalizerContract $normalizer,
        AssetMetadataRepositoryContract $repository
    ): int {
        $assetIdOption = $this->option('asset-id');
        $limit = max(0, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');

        $assetsQuery = Asset::query()->select(['id']);

        if ($assetIdOption !== null && trim((string) $assetIdOption) !== '') {
            $assetsQuery->where('id', (int) $assetIdOption);
        }

        if ($limit > 0) {
            $assetsQuery->limit($limit);
        }

        $assets = $assetsQuery->orderBy('id')->get();
        if ($assets->isEmpty()) {
            $this->warn('No assets matched the request.');

            return self::SUCCESS;
        }

        $processed = 0;
        $written = 0;
        $skipped = 0;

        foreach ($assets as $asset) {
            $processed++;
            $raw = AssetMetadataRaw::query()
                ->where('asset_id', $asset->id)
                ->latest('id')
                ->first();

            if ($raw === null) {
                $skipped++;
                $this->line("asset {$asset->id}: skipped (no raw metadata)");

                continue;
            }

            $rawBundle = new RawMetadataBundle(
                payload: is_array($raw->payload) ? $raw->payload : [],
                source: (string) $raw->source,
                toolVersion: $raw->tool_version,
                schemaVersion: null,
                hash: $raw->payload_hash
            );

            $normalized = $normalizer->normalize($rawBundle);
            $this->line(sprintf(
                'asset %d: %s (%s)',
                $asset->id,
                $dryRun ? 'would normalize' : 'normalized',
                $normalized->schemaVersion
            ));

            if ($dryRun) {
                continue;
            }

            $repository->storeNormalized(
                AssetId::from((int) $asset->id),
                $normalized,
                new MetadataProvenance(
                    source: 'renormalizer',
                    toolVersion: $raw->tool_version,
                    recordedAt: now()->toISOString(),
                    context: [
                        'command' => 'prophoto-assets:renormalize',
                        'raw_record_id' => $raw->id,
                    ]
                )
            );

            $written++;
        }

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['processed', $processed],
                ['written', $written],
                ['skipped', $skipped],
                ['dry_run', $dryRun ? 'yes' : 'no'],
            ]
        );

        return self::SUCCESS;
    }
}

