<?php

namespace ProPhoto\Ingest\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ProPhoto\Ingest\Http\Requests\ApplyTagRequest;
use ProPhoto\Ingest\Http\Requests\BatchFileUpdateRequest;
use ProPhoto\Ingest\Http\Requests\MatchCalendarRequest;
use ProPhoto\Ingest\Http\Requests\RegisterFilesRequest;
use ProPhoto\Ingest\Http\Requests\UploadFileRequest;
use ProPhoto\Assets\Models\Asset;
use ProPhoto\Ingest\Models\IngestFile;
use ProPhoto\Ingest\Models\IngestImageTag;
use ProPhoto\Ingest\Models\UploadSession;
use ProPhoto\Ingest\Services\Calendar\CalendarMatcherService;
use ProPhoto\Ingest\Services\Calendar\CalendarTokenService;
use ProPhoto\Ingest\Services\UploadSessionService;

/**
 * IngestController
 *
 * HTTP layer for the ProPhoto Ingest module.
 * Handles the API endpoints that bridge the frontend metadata extraction
 * (MetadataExtractor.ts) with the backend calendar matching, file
 * registration, background upload, and tagging services.
 *
 * Routes are registered by IngestServiceProvider via routes/api.php.
 *
 * Sprint 2: matchCalendar, sessionProgress, confirmSession
 * Sprint 3: registerFiles, uploadFile, applyTag, removeTag, batchUpdateFiles
 */
class IngestController extends Controller
{
    public function __construct(
        protected CalendarMatcherService $calendarMatcher,
        protected CalendarTokenService   $tokenService,
        protected UploadSessionService   $sessionService,
    ) {}

    // ─── POST /api/ingest/match-calendar ──────────────────────────────────────

    /**
     * Accept extracted image metadata, run calendar matching, and create
     * a new UploadSession. Returns ranked calendar matches (if any) and
     * the session ID for subsequent file uploads.
     *
     * Request body:
     *   {
     *     studio_id: int,
     *     user_id:   int,
     *     metadata:  ImageMetadata[]
     *   }
     *
     * Success response (200):
     *   {
     *     upload_session_id:  string (UUID),
     *     matches:            CalendarMatch[],
     *     no_match:           bool,
     *     timestamp_range:    { earliest: string|null, latest: string|null },
     *     images_analyzed:    int,
     *     calendar_connected: bool,
     *   }
     *
     * Error response (422): validation failure
     * Error response (500): unexpected service failure
     */
    public function matchCalendar(MatchCalendarRequest $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $metadata  = $request->input('metadata', []);
        $studioId  = (int) $request->input('studio_id');
        $userId    = (int) $request->input('user_id');

        // ── 1. Run calendar matching (if user has connected calendar) ──────────
        $calendarConnected = $this->tokenService->isConnected($user);
        $matchResult = [
            'matches'          => [],
            'no_match'         => true,
            'timestamp_range'  => ['earliest' => null, 'latest' => null],
            'images_analyzed'  => count($metadata),
        ];

        if ($calendarConnected) {
            try {
                $matchResult = $this->calendarMatcher->matchImages($metadata, $user);
            } catch (\Throwable $e) {
                // Calendar matching failure should not block session creation.
                // Log and fall through with empty matches.
                Log::warning('IngestController: Calendar matching failed, continuing without match', [
                    'user_id'  => $user->id,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        // ── 2. Determine best match for session creation ───────────────────────
        $bestMatch  = $matchResult['matches'][0] ?? null;
        $sessionAttributes = [
            'studio_id' => $studioId,
            'user_id'   => $userId,
        ];

        if ($bestMatch !== null) {
            $sessionAttributes = array_merge($sessionAttributes, [
                'calendar_event_id'         => $bestMatch['event_id'],
                'calendar_provider'         => 'google',
                'calendar_match_confidence' => $bestMatch['confidence'],
                'calendar_match_evidence'   => $bestMatch['evidence'],
            ]);
        }

        // ── 3. Create the UploadSession ───────────────────────────────────────
        try {
            $session = $this->sessionService->createSession($sessionAttributes);
        } catch (\Throwable $e) {
            Log::error('IngestController: Failed to create UploadSession', [
                'user_id'  => $user->id,
                'studio_id' => $studioId,
                'error'    => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to create upload session. Please try again.',
            ], 500);
        }

        // ── 4. Return combined result to frontend ─────────────────────────────
        return response()->json([
            'upload_session_id'  => $session->id,
            'matches'            => $matchResult['matches'],
            'no_match'           => $matchResult['no_match'],
            'timestamp_range'    => $matchResult['timestamp_range'],
            'images_analyzed'    => $matchResult['images_analyzed'],
            'calendar_connected' => $calendarConnected,
        ]);
    }

    // ─── GET /api/ingest/sessions/{sessionId}/progress ───────────────────────

    /**
     * Poll the upload progress for an active session.
     * Called by the frontend every N seconds while files are uploading.
     *
     * Response (200):
     *   {
     *     session_id:           string,
     *     status:               string,
     *     file_count:           int,
     *     completed_file_count: int,
     *     percent_complete:     int,
     *     is_uploading:         bool,
     *     gallery_id:           int|null,
     *   }
     */
    public function sessionProgress(string $sessionId): JsonResponse
    {
        try {
            $progress = $this->sessionService->getProgress($sessionId);
            return response()->json($progress);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => 'Session not found.'], 404);
        }
    }

    // ─── POST /api/ingest/sessions/{sessionId}/confirm ───────────────────────

    /**
     * Confirm an upload session, optionally setting the target gallery.
     * Triggers downstream asset creation.
     *
     * Idempotent: if the session is already confirmed or completed, returns 200
     * with the current state and "already_processed": true — no event is
     * re-dispatched and no assets are duplicated.
     *
     * Request body:
     *   { gallery_id?: int }
     *
     * Response (200) — fresh confirmation:
     *   {
     *     session_id:        string,
     *     status:            "confirmed",
     *     gallery_id:        int|null,
     *     already_processed: false
     *   }
     *
     * Response (200) — idempotent (session was already confirmed/completed):
     *   {
     *     session_id:        string,
     *     status:            "confirmed"|"completed"|"failed",
     *     gallery_id:        int|null,
     *     already_processed: true
     *   }
     *
     * Response (422) — session in an unconfirmable state (initiated/failed/cancelled):
     *   { error: "Cannot confirm session [id] with status [status]." }
     */
    public function confirmSession(string $sessionId): JsonResponse
    {
        $galleryId       = request()->input('gallery_id');
        $statusBefore    = null;

        try {
            // Capture status before the call so we can detect the idempotent path
            $existing     = \ProPhoto\Ingest\Models\UploadSession::find($sessionId);
            $statusBefore = $existing?->status;

            $session = $this->sessionService->confirmSession(
                $sessionId,
                galleryId: $galleryId ? (int) $galleryId : null,
            );

            $alreadyProcessed = in_array($statusBefore, [
                \ProPhoto\Ingest\Models\UploadSession::STATUS_CONFIRMED,
                \ProPhoto\Ingest\Models\UploadSession::STATUS_COMPLETED,
            ], true);

            return response()->json([
                'session_id'        => $session->id,
                'status'            => $session->status,
                'gallery_id'        => $session->gallery_id,
                'already_processed' => $alreadyProcessed,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    // ─── GET /api/ingest/sessions/{sessionId}/preview-status ────────────────

    /**
     * Poll asset creation progress after the session is confirmed.
     * The frontend polls this while the IngestSessionConfirmedListener
     * processes files in the background.
     *
     * Response (200):
     *   {
     *     session_id:     string,
     *     session_status: string,          // 'confirmed' | 'completed' | 'failed'
     *     total_files:    int,
     *     assets_created: int,
     *     is_complete:    bool,
     *     thumbnails: [
     *       { asset_id: string, thumb_path: string|null }
     *     ]
     *   }
     */
    public function previewStatus(string $sessionId): JsonResponse
    {
        $session = UploadSession::find($sessionId);
        if (! $session) {
            return response()->json(['error' => 'Session not found.'], 404);
        }

        // Count assets whose metadata references this session_id.
        // Wrapped in try/catch: the assets table lives in prophoto-assets and
        // may not exist in environments where that package is not installed.
        $assetsCreated = 0;
        $thumbnails    = [];

        try {
            $assetsCreated = Asset::whereJsonContains('metadata->session_id', $sessionId)->count();

            $thumbnails = Asset::whereJsonContains('metadata->session_id', $sessionId)
                ->get(['id', 'metadata'])
                ->map(fn (Asset $a) => [
                    'asset_id'   => $a->id,
                    'thumb_path' => $a->metadata['storage_key_thumb'] ?? null,
                ])
                ->values()
                ->toArray();
        } catch (\Throwable) {
            // Asset table unavailable — return safe defaults
        }

        $isComplete = in_array($session->status, [
            UploadSession::STATUS_COMPLETED,
            UploadSession::STATUS_FAILED,
        ], true);

        return response()->json([
            'session_id'     => $session->id,
            'session_status' => $session->status,
            'total_files'    => $session->file_count,
            'assets_created' => $assetsCreated,
            'is_complete'    => $isComplete,
            'thumbnails'     => $thumbnails,
        ]);
    }

    // ── Sprint 4 ──────────────────────────────────────────────────────────────

    // ─── GET /ingest ──────────────────────────────────────────────────────────

    /**
     * Render the IngestEntrypoint Inertia page.
     * This is the entry point for the entire ingest workflow — it renders the
     * React page that manages the 3-stage flow (initiate → match → gallery).
     *
     * Response: Inertia render of Pages/Ingest/IngestEntrypoint
     */
    public function entrypoint(Request $request): \Inertia\Response
    {
        $user = Auth::user();

        return \Inertia\Inertia::render('Ingest/IngestEntrypoint', [
            'studioId'          => $user->studio_id ?? 1,
            'userId'            => $user->id,
            'availableTags'     => [], // populated from prophoto-assets tag library in Phase 1c
            'calendarConnected' => $this->tokenService->isConnected($user),
        ]);
    }

    // ─── DELETE /api/ingest/sessions/{sessionId}/unlink-calendar ─────────────

    /**
     * Remove the calendar event linkage from a session.
     * Called when the photographer clicks "Unlink session" in CalendarTab.
     *
     * Response (200): { session_id: string, calendar_event_id: null }
     */
    public function unlinkCalendar(string $sessionId): JsonResponse
    {
        $session = UploadSession::find($sessionId);
        if (! $session) {
            return response()->json(['error' => 'Session not found.'], 404);
        }

        $session->update([
            'calendar_event_id'         => null,
            'calendar_provider'         => null,
            'calendar_match_confidence' => null,
            'calendar_match_evidence'   => null,
        ]);

        return response()->json([
            'session_id'       => $session->id,
            'calendar_event_id' => null,
        ]);
    }

    // ── Sprint 3 ──────────────────────────────────────────────────────────────

    // ─── POST /api/ingest/sessions/{sessionId}/files ──────────────────────────

    /**
     * Register all files in the upload session before bytes are transferred.
     * Returns a UUID per file so the frontend UploadManager can reference them.
     *
     * Called once by IngestGallery on mount, before uploading begins.
     *
     * Request body:
     *   { files: [{ filename, file_size, file_type, exif }] }
     *
     * Response (201):
     *   { files: [{ file_id: string, filename: string }] }
     */
    public function registerFiles(RegisterFilesRequest $request, string $sessionId): JsonResponse
    {
        $session = UploadSession::find($sessionId);
        if (! $session) {
            return response()->json(['error' => 'Session not found.'], 404);
        }

        $registered = [];

        foreach ($request->input('files', []) as $fileData) {
            try {
                $ingestFile = $this->sessionService->registerFile($sessionId, [
                    'filename'  => $fileData['filename'],
                    'file_size' => $fileData['file_size'],
                    'file_type' => $fileData['file_type'],
                    'exif_data' => $fileData['exif'] ?? [],
                ]);

                $registered[] = [
                    'file_id'  => $ingestFile->id,
                    'filename' => $ingestFile->filename,
                ];
            } catch (\Throwable $e) {
                Log::warning('IngestController: Failed to register file', [
                    'session_id' => $sessionId,
                    'filename'   => $fileData['filename'] ?? 'unknown',
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        return response()->json(['files' => $registered], 201);
    }

    // ─── POST /api/ingest/sessions/{sessionId}/upload ─────────────────────────

    /**
     * Receive a single file binary upload from the UploadManager XHR.
     * Stores the file in the ingest disk and marks the IngestFile as uploaded.
     *
     * The file_id must reference a pre-registered IngestFile (via registerFiles).
     *
     * Request: multipart/form-data { file: File, file_id: UUID }
     *
     * Response (200): { file_id: string, status: 'uploaded' }
     */
    public function uploadFile(UploadFileRequest $request, string $sessionId): JsonResponse
    {
        $fileId = $request->input('file_id');

        $ingestFile = IngestFile::find($fileId);
        if (! $ingestFile || $ingestFile->upload_session_id !== $sessionId) {
            return response()->json(['error' => 'File not found in session.'], 404);
        }

        // Already uploaded — idempotent
        if ($ingestFile->upload_status === IngestFile::STATUS_COMPLETED) {
            return response()->json(['file_id' => $fileId, 'status' => 'uploaded']);
        }

        try {
            $uploadedFile = $request->file('file');
            $path = $uploadedFile->storeAs(
                "ingest/{$sessionId}",
                $ingestFile->id . '_' . $ingestFile->filename,
                ['disk' => 'local'],
            );

            $ingestFile->update(['storage_path' => $path]);
            $this->sessionService->recordFileUploaded($fileId);

            return response()->json([
                'file_id' => $fileId,
                'status'  => 'uploaded',
            ]);
        } catch (\Throwable $e) {
            Log::error('IngestController: File upload failed', [
                'session_id' => $sessionId,
                'file_id'    => $fileId,
                'error'      => $e->getMessage(),
            ]);

            $this->sessionService->recordFileFailed($fileId, $e->getMessage());

            return response()->json(['error' => 'Upload failed. Please retry.'], 500);
        }
    }

    // ─── POST /api/ingest/sessions/{sessionId}/files/{fileId}/tags ───────────

    /**
     * Apply a single tag to a file.
     * Idempotent — applying the same tag twice is a no-op.
     *
     * Request body: { tag: string, tag_type: 'metadata'|'calendar'|'user' }
     *
     * Response (201): { file_id, tag, tag_type }
     */
    public function applyTag(ApplyTagRequest $request, string $sessionId, string $fileId): JsonResponse
    {
        $ingestFile = IngestFile::find($fileId);
        if (! $ingestFile || $ingestFile->upload_session_id !== $sessionId) {
            return response()->json(['error' => 'File not found in session.'], 404);
        }

        try {
            $tag = $this->sessionService->applyTag(
                $fileId,
                tag:     $request->input('tag'),
                tagType: $request->input('tag_type'),
            );

            return response()->json([
                'file_id'  => $fileId,
                'tag'      => $tag->tag,
                'tag_type' => $tag->tag_type,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    // ─── DELETE /api/ingest/sessions/{sessionId}/files/{fileId}/tags/{tag} ───

    /**
     * Remove a tag from a file.
     * Returns 204 No Content on success (including if the tag didn't exist).
     */
    public function removeTag(string $sessionId, string $fileId, string $tag): JsonResponse
    {
        $ingestFile = IngestFile::find($fileId);
        if (! $ingestFile || $ingestFile->upload_session_id !== $sessionId) {
            return response()->json(['error' => 'File not found in session.'], 404);
        }

        $this->sessionService->removeTag($fileId, urldecode($tag));

        return response()->json(null, 204);
    }

    // ─── PATCH /api/ingest/sessions/{sessionId}/files/batch ──────────────────

    /**
     * Apply a batch update (cull toggle or star rating) to multiple files.
     * Used by handleCull() and handleStar() in IngestGallery.tsx.
     *
     * Request body:
     *   { ids: UUID[], updates: { culled?: bool, rating?: int } }
     *
     * Response (200):
     *   { updated: int }
     */
    public function batchUpdateFiles(BatchFileUpdateRequest $request, string $sessionId): JsonResponse
    {
        $ids     = $request->input('ids', []);
        $updates = $request->input('updates', []);

        if (empty($ids) || empty($updates)) {
            return response()->json(['updated' => 0]);
        }

        // ── Sprint 7: N+1 fix ─────────────────────────────────────────────────
        // Fetch all target files in a single query, then apply updates in bulk
        // via DB::table() rather than one Eloquent query + save per row.
        $files = IngestFile::whereIn('id', $ids)
            ->where('upload_session_id', $sessionId)
            ->get(['id']);

        if ($files->isEmpty()) {
            return response()->json(['updated' => 0]);
        }

        $validIds = $files->pluck('id')->toArray();
        $patch    = [];

        if (array_key_exists('culled', $updates)) {
            $patch['culled'] = (bool) $updates['culled'];
        }

        if (array_key_exists('rating', $updates)) {
            $patch['rating'] = max(0, min(5, (int) $updates['rating']));
        }

        $updated = 0;

        if (! empty($patch)) {
            $patch['updated_at'] = now()->toDateTimeString();
            $updated = \Illuminate\Support\Facades\DB::table('ingest_files')
                ->whereIn('id', $validIds)
                ->update($patch);
        }

        return response()->json(['updated' => $updated]);
    }
}
