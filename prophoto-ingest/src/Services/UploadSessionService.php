<?php

namespace ProPhoto\Ingest\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use ProPhoto\Ingest\Events\IngestSessionConfirmed;
use ProPhoto\Ingest\Models\IngestFile;
use ProPhoto\Ingest\Models\IngestImageTag;
use ProPhoto\Ingest\Models\UploadSession;

/**
 * UploadSessionService
 *
 * Manages the full lifecycle of an UploadSession:
 *   - Creating a new session (after metadata extraction + calendar matching)
 *   - Recording individual file uploads as they complete
 *   - Applying tags to files during the ingest gallery workflow
 *   - Completing a session (triggers downstream asset creation)
 *
 * All state transitions are explicit and logged for observability.
 *
 * Story 1a.6 — Sprint 1
 */
class UploadSessionService
{
    // ─── Session Creation ─────────────────────────────────────────────────────

    /**
     * Create a new UploadSession for a batch file upload.
     *
     * @param  array{
     *   studio_id: int,
     *   user_id: int,
     *   calendar_event_id?: string|null,
     *   calendar_provider?: string|null,
     *   calendar_match_confidence?: float|null,
     *   calendar_match_evidence?: array|null,
     * }  $attributes
     */
    public function createSession(array $attributes): UploadSession
    {
        $session = UploadSession::create([
            'studio_id'                 => $attributes['studio_id'],
            'user_id'                   => $attributes['user_id'],
            'calendar_event_id'         => $attributes['calendar_event_id'] ?? null,
            'calendar_provider'         => $attributes['calendar_provider'] ?? null,
            'calendar_match_confidence' => $attributes['calendar_match_confidence'] ?? null,
            'calendar_match_evidence'   => $attributes['calendar_match_evidence'] ?? null,
            'status'                    => UploadSession::STATUS_INITIATED,
        ]);

        Log::info('UploadSession: created', [
            'session_id'        => $session->id,
            'studio_id'         => $session->studio_id,
            'has_calendar_match' => $session->hasCalendarMatch(),
        ]);

        return $session;
    }

    // ─── File Registration ────────────────────────────────────────────────────

    /**
     * Register a file within an upload session before it starts uploading.
     * Creates the IngestFile record and auto-derives metadata tags from EXIF.
     *
     * @param  array{
     *   original_filename: string,
     *   file_size_bytes: int,
     *   file_type: string,
     *   mime_type?: string|null,
     *   exif_data?: array|null,
     * }  $fileAttributes
     */
    public function registerFile(string $sessionId, array $fileAttributes): IngestFile
    {
        $session = $this->findOrFail($sessionId);

        // Accept both 'original_filename' and the shorter 'filename' alias
        $originalFilename = $fileAttributes['original_filename']
            ?? $fileAttributes['filename']
            ?? '';

        $file = IngestFile::create([
            'upload_session_id' => $session->id,
            'original_filename' => $originalFilename,
            'file_size_bytes'   => $fileAttributes['file_size_bytes'] ?? $fileAttributes['file_size'] ?? 0,
            'file_type'         => $fileAttributes['file_type'],
            'mime_type'         => $fileAttributes['mime_type'] ?? null,
            'exif_data'         => $fileAttributes['exif_data'] ?? null,
            'upload_status'     => IngestFile::STATUS_PENDING,
            'culled'            => false,
            'rating'            => 0,
        ]);

        // Auto-apply metadata-derived tags from EXIF
        $this->applyMetadataTags($file);

        // Update session aggregate counts
        DB::table('upload_sessions')
            ->where('id', $session->id)
            ->increment('file_count');

        DB::table('upload_sessions')
            ->where('id', $session->id)
            ->increment('total_size_bytes', $fileAttributes['file_size_bytes'] ?? $fileAttributes['file_size'] ?? 0);

        return $file;
    }

    // ─── File Upload Recording ────────────────────────────────────────────────

    /**
     * Mark a file as successfully uploaded.
     * Idempotent — safe to call again if file was already marked complete.
     */
    public function recordFileUploaded(string $fileId): void
    {
        $file = IngestFile::findOrFail($fileId);

        // Idempotency guard
        if ($file->upload_status === IngestFile::STATUS_COMPLETED) {
            return;
        }

        $file->update([
            'upload_status' => IngestFile::STATUS_COMPLETED,
            'uploaded_at'   => now(),
        ]);

        // Increment session completed count
        DB::table('upload_sessions')
            ->where('id', $file->upload_session_id)
            ->increment('completed_file_count');

        // Transition session status to 'uploading' on first file completion
        $session = UploadSession::find($file->upload_session_id);
        if ($session && $session->status === UploadSession::STATUS_INITIATED) {
            $session->update([
                'status'            => UploadSession::STATUS_UPLOADING,
                'upload_started_at' => now(),
            ]);
        }
    }

    /**
     * Mark a file as failed.
     */
    public function recordFileFailed(string $fileId, ?string $reason = null): void
    {
        $file = IngestFile::findOrFail($fileId);

        $file->update(['upload_status' => IngestFile::STATUS_FAILED]);

        Log::warning('UploadSession: file upload failed', [
            'file_id'    => $fileId,
            'session_id' => $file->upload_session_id,
            'filename'   => $file->original_filename,
            'reason'     => $reason,
        ]);
    }

    // ─── Tagging ──────────────────────────────────────────────────────────────

    /**
     * Apply a tag to a file. Idempotent — duplicate tags are ignored.
     *
     * @param  'metadata'|'calendar'|'user'  $tagType
     */
    public function applyTag(string $fileId, string $tag, string $tagType = 'user'): IngestImageTag
    {
        $tag = mb_strtolower(trim($tag));

        if (empty($tag)) {
            throw new \InvalidArgumentException('Tag cannot be empty.');
        }

        // findOrCreate — ignore duplicate (unique constraint)
        return IngestImageTag::firstOrCreate(
            ['ingest_file_id' => $fileId, 'tag' => $tag],
            ['tag_type' => $tagType]
        );
    }

    /**
     * Remove a tag from a file.
     */
    public function removeTag(string $fileId, string $tag): void
    {
        IngestImageTag::where('ingest_file_id', $fileId)
            ->where('tag', mb_strtolower(trim($tag)))
            ->delete();
    }

    /**
     * Get all tags for a file.
     *
     * @return list<array{tag: string, tag_type: string}>
     */
    public function getTagsForFile(string $fileId): array
    {
        return IngestImageTag::where('ingest_file_id', $fileId)
            ->orderBy('tag_type')
            ->orderBy('tag')
            ->get(['tag', 'tag_type'])
            ->toArray();
    }

    // ─── Session Completion ───────────────────────────────────────────────────

    /**
     * Get current upload progress for a session.
     *
     * @return array{
     *   session_id: string,
     *   status: string,
     *   file_count: int,
     *   completed_file_count: int,
     *   percent_complete: int,
     *   is_uploading: bool,
     * }
     */
    public function getProgress(string $sessionId): array
    {
        $session = $this->findOrFail($sessionId);

        $failedCount = $session->files()
            ->where('upload_status', IngestFile::STATUS_FAILED)
            ->count();

        return [
            'session_id'           => $session->id,
            'status'               => $session->status,
            'file_count'           => $session->file_count,
            'completed_file_count' => $session->completed_file_count,
            'failed_file_count'    => $failedCount,
            'percent_complete'     => $session->percent_complete,
            'is_uploading'         => $session->isUploading(),
            'gallery_id'           => $session->gallery_id,
        ];
    }

    /**
     * Mark a session as confirmed by the user.
     * This is the trigger for downstream asset creation via events.
     *
     * Idempotency: if the session is already confirmed or completed, return the
     * current state without re-dispatching IngestSessionConfirmed (which would
     * create duplicate assets). This makes the endpoint safe for Postman retries
     * and re-runs against a sandbox that was not fully reset.
     *
     * @throws \RuntimeException if session is in an unrecoverable / pre-upload state
     */
    public function confirmSession(string $sessionId, ?int $galleryId = null): UploadSession
    {
        $session = $this->findOrFail($sessionId);

        // ── Idempotency guard ─────────────────────────────────────────────────
        // Already confirmed or completed — return current state, no re-dispatch.
        $alreadyDoneStatuses = [
            UploadSession::STATUS_CONFIRMED,
            UploadSession::STATUS_COMPLETED,
        ];

        if (in_array($session->status, $alreadyDoneStatuses, true)) {
            Log::info('UploadSession: confirmSession called on already-processed session (idempotent no-op)', [
                'session_id' => $sessionId,
                'status'     => $session->status,
            ]);

            return $session;
        }

        // ── Gate: only uploading or tagging can be confirmed ──────────────────
        $confirmableStatuses = [
            UploadSession::STATUS_UPLOADING,
            UploadSession::STATUS_TAGGING,
        ];

        if (! in_array($session->status, $confirmableStatuses, true)) {
            throw new \RuntimeException(
                "Cannot confirm session [{$sessionId}] with status [{$session->status}]."
            );
        }

        $session->update([
            'status'       => UploadSession::STATUS_CONFIRMED,
            'gallery_id'   => $galleryId ?? $session->gallery_id,
            'confirmed_at' => now(),
        ]);

        Log::info('UploadSession: confirmed by user', [
            'session_id'  => $session->id,
            'studio_id'   => $session->studio_id,
            'file_count'  => $session->file_count,
            'gallery_id'  => $session->gallery_id,
        ]);

        // ── Dispatch event to trigger asset creation pipeline ─────────────────
        $fresh = $session->fresh();

        Event::dispatch(new IngestSessionConfirmed(
            sessionId:               $fresh->id,
            studioId:                $fresh->studio_id,
            userId:                  $fresh->user_id,
            occurredAt:              now()->toISOString(),
            calendarEventId:         $fresh->calendar_event_id,
            calendarProvider:        $fresh->calendar_provider,
            calendarMatchConfidence: $fresh->calendar_match_confidence,
            galleryId:               $fresh->gallery_id,
        ));

        return $fresh;
    }

    /**
     * Mark the session as fully completed (assets created, gallery ready).
     * Called by the AssetCreationListener after processing is done.
     */
    public function markCompleted(string $sessionId): void
    {
        UploadSession::where('id', $sessionId)->update([
            'status'               => UploadSession::STATUS_COMPLETED,
            'upload_completed_at'  => now(),
        ]);
    }

    /**
     * Mark the session as failed.
     */
    public function markFailed(string $sessionId, ?string $reason = null): void
    {
        UploadSession::where('id', $sessionId)->update([
            'status' => UploadSession::STATUS_FAILED,
        ]);

        Log::error('UploadSession: session failed', [
            'session_id' => $sessionId,
            'reason'     => $reason,
        ]);
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

    protected function findOrFail(string $sessionId): UploadSession
    {
        $session = UploadSession::find($sessionId);

        if (! $session) {
            throw new \RuntimeException("Upload session [{$sessionId}] not found.");
        }

        return $session;
    }

    /**
     * Auto-derive tags from EXIF data and apply them as 'metadata' type tags.
     * Makes metadata immediately searchable/filterable in the gallery.
     */
    protected function applyMetadataTags(IngestFile $file): void
    {
        $exif = $file->exif_data ?? [];

        // ISO tag: e.g. "iso-400"
        if (! empty($exif['iso'])) {
            $this->applyTag($file->id, "iso-{$exif['iso']}", IngestImageTag::TYPE_METADATA);
        }

        // Aperture tag: e.g. "f1.8"
        if (! empty($exif['aperture'])) {
            $aperture = number_format((float) $exif['aperture'], 1);
            $this->applyTag($file->id, "f{$aperture}", IngestImageTag::TYPE_METADATA);
        }

        // Focal length tag: e.g. "50mm"
        if (! empty($exif['focalLength'])) {
            $this->applyTag($file->id, "{$exif['focalLength']}mm", IngestImageTag::TYPE_METADATA);
        }

        // Camera model tag: e.g. "canon-5d-mark-iv"
        if (! empty($exif['camera'])) {
            $cameraTag = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $exif['camera']));
            $this->applyTag($file->id, $cameraTag, IngestImageTag::TYPE_METADATA);
        }
    }
}
