<?php

namespace ProPhoto\Ingest\Listeners;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ProPhoto\Assets\Services\Assets\AssetCreationService;
use ProPhoto\Ingest\Events\IngestSessionConfirmed;
use ProPhoto\Ingest\Jobs\GenerateAssetThumbnail;
use ProPhoto\Ingest\Models\IngestFile;
use ProPhoto\Ingest\Services\UploadSessionService;

/**
 * IngestSessionConfirmedListener
 *
 * Handles the IngestSessionConfirmed event. Iterates all uploaded IngestFiles
 * in the session and creates canonical Asset records via AssetCreationService.
 *
 * After each asset is created, a GenerateAssetThumbnail job is dispatched
 * to the queue for async derivative generation.
 *
 * On completion, marks the UploadSession as STATUS_COMPLETED.
 * On any uncaught exception, marks the session as STATUS_FAILED.
 *
 * Sprint 5 — Story 1c.2
 */
class IngestSessionConfirmedListener
{
    public function __construct(
        private readonly AssetCreationService  $assetCreationService,
        private readonly UploadSessionService  $sessionService,
    ) {}

    public function handle(IngestSessionConfirmed $event): void
    {
        $sessionId = $event->sessionId;

        Log::info('IngestSessionConfirmedListener: starting asset creation', [
            'session_id' => $sessionId,
            'studio_id'  => $event->studioId,
        ]);

        try {
            $files = IngestFile::where('upload_session_id', $sessionId)
                ->where('upload_status', IngestFile::STATUS_COMPLETED)
                ->where('culled', false)   // 'culled' is the actual DB column; 'is_culled' is a write-only alias
                ->get();

            if ($files->isEmpty()) {
                Log::warning('IngestSessionConfirmedListener: no completed, non-culled files found', [
                    'session_id' => $sessionId,
                ]);
                $this->sessionService->markCompleted($sessionId);
                return;
            }

            $createdCount = 0;
            $failedCount  = 0;

            foreach ($files as $ingestFile) {
                try {
                    $this->processFile($ingestFile, $event);
                    $createdCount++;
                } catch (\Throwable $e) {
                    $failedCount++;
                    Log::error('IngestSessionConfirmedListener: failed to create asset for file', [
                        'session_id' => $sessionId,
                        'file_id'    => $ingestFile->id,
                        'filename'   => $ingestFile->original_filename,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }

            Log::info('IngestSessionConfirmedListener: asset creation complete', [
                'session_id'    => $sessionId,
                'created_count' => $createdCount,
                'failed_count'  => $failedCount,
            ]);

            $this->sessionService->markCompleted($sessionId);

        } catch (\Throwable $e) {
            Log::error('IngestSessionConfirmedListener: session processing failed', [
                'session_id' => $sessionId,
                'error'      => $e->getMessage(),
            ]);
            $this->sessionService->markFailed($sessionId, $e->getMessage());
        }
    }

    /**
     * Create an Asset record for a single IngestFile, then dispatch the
     * thumbnail generation job for async derivative processing.
     */
    private function processFile(IngestFile $ingestFile, IngestSessionConfirmed $event): void
    {
        // Resolve the absolute path from storage
        $storagePath = $ingestFile->storage_path
            ?? "ingest/{$ingestFile->upload_session_id}/{$ingestFile->id}_{$ingestFile->original_filename}";

        $absolutePath = Storage::disk('local')->path($storagePath);

        // Build raw metadata payload from EXIF data stored at registration time
        $exif = $ingestFile->exif_data ?? [];
        $rawMetadata = array_filter([
            'filename'     => $ingestFile->original_filename,
            'file_size'    => $ingestFile->file_size_bytes,
            'file_type'    => $ingestFile->file_type,
            'iso'          => $exif['iso'] ?? null,
            'aperture'     => $exif['aperture'] ?? null,
            'focal_length' => $exif['focalLength'] ?? null,
            'camera'       => $exif['camera'] ?? null,
            'lens'         => $exif['lens'] ?? null,
            'captured_at'  => $exif['dateTime'] ?? null,
            'gps_lat'      => $exif['gpsLat'] ?? null,
            'gps_lng'      => $exif['gpsLng'] ?? null,
        ], fn ($v) => $v !== null);

        $asset = $this->assetCreationService->createFromFile($absolutePath, [
            'studio_id'            => (string) $event->studioId,
            'original_filename'    => $ingestFile->original_filename,
            'mime_type'            => $ingestFile->mime_type ?? $ingestFile->file_type,
            'captured_at'          => isset($exif['dateTime'])
                                        ? Carbon::parse($exif['dateTime'])->toDateTimeString()
                                        : null,
            'ingested_at'          => $ingestFile->uploaded_at ?? now(),
            'raw_metadata'         => $rawMetadata,
            'metadata_source'      => 'prophoto-ingest',
            'metadata_tool_version' => '1.0.0',
            'metadata_context'     => [
                'ingest_file_id' => $ingestFile->id,
                'session_id'     => $event->sessionId,
                'calendar_event' => $event->calendarEventId,
            ],
            'metadata' => [
                'ingest_file_id'          => $ingestFile->id,
                'session_id'              => $event->sessionId,
                'calendar_event_id'       => $event->calendarEventId,
                'calendar_match_confidence' => $event->calendarMatchConfidence,
                'rating'                  => $ingestFile->rating ?? 0,
                'tags'                    => $ingestFile->tags()
                                                ->pluck('tag')
                                                ->values()
                                                ->toArray(),
            ],
        ]);

        // Dispatch async thumbnail generation
        GenerateAssetThumbnail::dispatch($asset->id, $storagePath);
    }
}
