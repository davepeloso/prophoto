<?php

namespace ProPhoto\Gallery\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillGalleryImageAssetIdsCommand extends Command
{
    protected $signature = 'prophoto-gallery:backfill-asset-ids
        {--dry-run : Preview matches without updating rows}
        {--chunk=200 : Number of image rows to process per chunk}
        {--limit=0 : Stop after N processed rows (0 = no limit)}
        {--only-null : Only process rows where images.asset_id is NULL}';

    protected $description = 'Backfill images.asset_id by matching gallery images to canonical assets.';

    public function handle(): int
    {
        $imagesTable = 'images';
        $assetsTable = (string) config('prophoto-assets.tables.assets', 'assets');

        if (!Schema::hasTable($imagesTable)) {
            $this->error('Table "images" does not exist.');

            return self::FAILURE;
        }

        if (!Schema::hasColumn($imagesTable, 'asset_id')) {
            $this->error('Column "images.asset_id" is missing. Run migrations first.');

            return self::FAILURE;
        }

        if (!Schema::hasTable($assetsTable)) {
            $this->error(sprintf('Table "%s" does not exist. Install/migrate prophoto-assets first.', $assetsTable));

            return self::FAILURE;
        }

        $hasHashColumn = Schema::hasColumn($imagesTable, 'hash');
        $hasFilePathColumn = Schema::hasColumn($imagesTable, 'file_path');

        $dryRun = (bool) $this->option('dry-run');
        $onlyNull = (bool) $this->option('only-null');
        $chunkSize = max(1, (int) $this->option('chunk'));
        $limit = max(0, (int) $this->option('limit'));

        $selectColumns = ['id', 'asset_id', 'filename', 'file_size', 'mime_type'];
        if ($hasHashColumn) {
            $selectColumns[] = 'hash';
        }
        if ($hasFilePathColumn) {
            $selectColumns[] = 'file_path';
        }

        $query = DB::table($imagesTable)
            ->select($selectColumns)
            ->orderBy('id');

        if ($onlyNull) {
            $query->whereNull('asset_id');
        }

        $this->line(sprintf(
            'Starting backfill (%s, chunk=%d, limit=%d, only-null=%s)',
            $dryRun ? 'dry-run' : 'write',
            $chunkSize,
            $limit,
            $onlyNull ? 'yes' : 'no'
        ));

        $processed = 0;
        $matched = 0;
        $updated = 0;
        $wouldUpdate = 0;
        $ambiguous = 0;
        $notFound = 0;
        $stoppedByLimit = false;

        $now = now();

        $query->chunkById($chunkSize, function ($rows) use (
            $assetsTable,
            $dryRun,
            $limit,
            $now,
            &$processed,
            &$matched,
            &$updated,
            &$wouldUpdate,
            &$ambiguous,
            &$notFound,
            &$stoppedByLimit
        ) {
            foreach ($rows as $row) {
                if ($limit > 0 && $processed >= $limit) {
                    $stoppedByLimit = true;

                    return false;
                }

                $processed++;
                $match = $this->matchAssetForImage($row, $assetsTable);

                if ($match['status'] === 'matched') {
                    $matched++;

                    if ($dryRun) {
                        $wouldUpdate++;
                    } else {
                        DB::table('images')
                            ->where('id', $row->id)
                            ->update([
                                'asset_id' => $match['asset_id'],
                                'updated_at' => $now,
                            ]);

                        $updated++;
                    }
                    continue;
                }

                if ($match['status'] === 'ambiguous') {
                    $ambiguous++;
                    continue;
                }

                $notFound++;
            }

            return true;
        }, 'id');

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['processed', $processed],
                ['matched', $matched],
                ['updated', $updated],
                ['would_update', $wouldUpdate],
                ['ambiguous', $ambiguous],
                ['not_found', $notFound],
                ['dry_run', $dryRun ? 'yes' : 'no'],
                ['stopped_by_limit', $stoppedByLimit ? 'yes' : 'no'],
            ]
        );

        if ($dryRun) {
            $this->warn('Dry-run mode: no database rows were updated.');
        } else {
            $this->info('Backfill complete.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array{status: 'matched'|'ambiguous'|'none', asset_id?: int}
     */
    protected function matchAssetForImage(object $image, string $assetsTable): array
    {
        $hash = isset($image->hash) ? trim((string) $image->hash) : '';
        if ($hash !== '') {
            $match = $this->findSingleAssetId(
                DB::table($assetsTable)->where('checksum_sha256', $hash)
            );

            if ($match['status'] !== 'none') {
                return $match;
            }
        }

        $filePath = isset($image->file_path) ? trim((string) $image->file_path) : '';
        if ($filePath !== '') {
            $match = $this->findSingleAssetId(
                DB::table($assetsTable)->where('storage_key_original', $filePath)
            );

            if ($match['status'] !== 'none') {
                return $match;
            }
        }

        $filename = trim((string) ($image->filename ?? ''));
        if ($filename === '') {
            return ['status' => 'none'];
        }

        $query = DB::table($assetsTable)->where('original_filename', $filename);

        if (!empty($image->file_size)) {
            $query->where('bytes', (int) $image->file_size);
        }

        if (!empty($image->mime_type)) {
            $query->where('mime_type', (string) $image->mime_type);
        }

        return $this->findSingleAssetId($query);
    }

    /**
     * @return array{status: 'matched'|'ambiguous'|'none', asset_id?: int}
     */
    protected function findSingleAssetId($query): array
    {
        $ids = $query->limit(2)->pluck('id')->all();
        $count = count($ids);

        if ($count === 1) {
            return [
                'status' => 'matched',
                'asset_id' => (int) $ids[0],
            ];
        }

        if ($count > 1) {
            return ['status' => 'ambiguous'];
        }

        return ['status' => 'none'];
    }
}
