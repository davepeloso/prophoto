The goal is to implement an ExifTool-first metadata & preview extraction flow that replaces the fragile PHP exif_read_data parsing, speeds up "pseudo-uploads" (metadata + preview only), and provides normalized, JSON-ready EXIF metadata for later ingest. Your deliverables, tests, and acceptance criteria are listed below.
--- CONTEXT & GOAL

* • Replace/augment the existing MetadataExtractor service to invoke ExifTool for fast, robust metadata extraction and for embedded preview extraction.
* • Reduce custom parsing code by delegating normalization to ExifTool where possible. Keep targeted PHP parsing only for final normalization & mapping to the app schema.
* • Keep upload-time work minimal: store temp file, run ExifTool (metadata + preview), save lightweight ProxyImage record with preview path and metadata JSON. Defer heavy processing (resizing, final storage) to queued workers.
* • Prioritize speed, reliability, and observability. The system must scale to large batches (hundreds to thousands) of RAW files without heavy CPU or memory use at upload time.

⠀--- PRIMARY TASKS (full-stack, end-to-end)

1. 1 Create an ExifToolService in Laravel:
   * ◦ Single responsibility: run ExifTool, return structured JSON and optionally binary preview bytes.
   * ◦ Use Symfony Process or native proc_open with robust timeouts and streaming support.
   * ◦ Provide these methods:
     * ▪ extractMetadata(array $paths, array $options = []): array — batch metadata extraction returns associative array keyed by filename.
     * ▪ extractPreview(string $path, string $previewTag = null): string|false — returns raw JPEG bytes or writes to disk and returns path.
     * ▪ healthCheck(): bool — verifies exiftool binary is available and responsive.
   * ◦ Default flags to use (tunable):
     * ▪ -j (JSON output)
     * ▪ -n (numeric values where appropriate)
     * ▪ -G (group names) — optional
     * ▪ -fast or -fast2 when speed prioritized (explain tradeoffs in comments)
     * ▪ For preview extraction: -b -PreviewImage or -b -JpgFromRaw, fall back to -b -ThumbnailImage in sequence.
     * ▪ Example command:### exiftool -j -n -fast -charset filename=UTF8 -api QuickTimeUTC=1 "file1.ARW" "file2.NEF"
     * ▪
   * ◦ Sanitize all inputs and escape file paths.
2. 2 Replace MetadataExtractor::extract() flow:
   * ◦ Instead of PHP exif_read_data, call ExifToolService::extractMetadata for single or batch files.
   * ◦ Save ExifTool raw JSON into ProxyImage.metadata_raw and a normalized metadata JSON into ProxyImage.metadata (application schema).
   * ◦ Keep a small compatibility transform function to:
     * ▪ Map ExifTool fields to your schema keys (DateTimeOriginal → date_taken, FNumber → f_stop, ExposureTime → shutter_speed, ISOSpeedRatings → iso, GPSLatitude/GPSLongitude → gps_lat/gps_lng).
     * ▪ Convert ExifTool numeric strings to correct types if needed (exiftool -n already helps).
     * ▪ Extract camera make/model and slugify for path generation.
   * ◦ Avoid re-implementing camera-maker parsing logic; use ExifTool outputs for fractions, rational numbers, and made-up formatted strings.
3. 3 Implement preview handling at upload:
   * ◦ Use ExifTool to extract embedded preview in-memory or to temporary disk path.
   * ◦ Save preview as a small JPEG in your temp disk (e.g., temp/previews/{uuid}.jpg) and store path in ProxyImage.preview_path.
   * ◦ If no embedded preview found, fallback to a lightweight ImageMagick convert or Glide pipeline to generate a preview (document fallback).
   * ◦ Ensure preview generation is asynchronous/capped CPU so uploads stay fast.
4. 4 Batch/bulk optimization:
   * ◦ Provide a batch metadata extraction endpoint that accepts multiple temp file paths and returns metadata in one ExifTool call (reduces process startup overhead).
   * ◦ Where possible, pass multiple file paths to a single exiftool invocation.
5. 5 Queue & Ingest pipeline compatibility:
   * ◦ Ensure ProcessImageIngestJob uses ProxyImage.metadata (normalized) instead of raw exif arrays.
   * ◦ Remove or guard custom parsing functions (evalFraction, parseAperture, parseShutterSpeed) and keep them as unit-tested fallback utilities.
   * ◦ IngestProcessor should: build paths, write final storage, create Image record using the normalized metadata, and cleanup temp files.
6. 6 Observability & failure modes:
   * ◦ Add metrics and logs: exiftool call duration, number of files processed per call, errors, and fallback triggers.
   * ◦ ExifToolService must handle timeouts, crashes, malformed JSON, and missing binary. Provide clear exceptions and graceful fallbacks (return null metadata and mark ProxyImage with metadata_error).
   * ◦ Implement a retry/backoff for transient exiftool errors at the job level.
7. 7 Security & sanitization:
   * ◦ Validate file paths and ensure exiftool runs in a safe environment.
   * ◦ Escape shell arguments robustly (use Symfony Process).
   * ◦ Limit maximum file size for preview extraction memory buffers; if too large, write to temp file and stream to disk.
8. 8 Tests, CI, and documentation:
   * ◦ Unit tests for ExifToolService methods covering:
     * ▪ Normal JSON extraction (single & batch).
     * ▪ Preview extraction success & expected fallback.
     * ▪ Health check behavior when binary missing.
   * ◦ Integration tests for MetadataExtractor using fixture RAW/HEIC/JPEG files.
   * ◦ Performance test script that runs exiftool on N fixtures and reports median latency.
   * ◦ Add EXIF-to-schema mapping documentation and sample JSON in docs/ingest/exiftool.md.

⠀--- IMPLEMENTATION DETAILS & EXAMPLES

* • Example ExifTool JSON normalization snippet (pseudocode expected in implementation):
 "FileName": "AMB_0838.jpg",
  "Directory": "/Volumes/DAM/25-10-25/1710 Granville Ave/Output/ImageMagick",
  "FileSize": "2.2 MB",
  "FileModifyDate": "2025:10:25 13:35:44-07:00",
  "FileAccessDate": "2025:12:08 10:42:50-08:00",
  "FileInodeChangeDate": "2025:10:25 13:35:44-07:00",
  "FilePermissions": "-rw-rw-rw-",
  "FileType": "JPEG",
  "FileTypeExtension": "jpg",
  "MIMEType": "image/jpeg",
  "JFIFVersion": 1.01,
  "ExifByteOrder": "Little-endian (Intel, II)",
  "Make": "NIKON CORPORATION",
  "Model": "NIKON Z 6_2",
  "XResolution": 300,
  "YResolution": 300,
  "ResolutionUnit": "inches",
  "Software": "Capture One Macintosh",
  "ExposureTime": "1/20",
  "FNumber": 7.1,
  "ExposureProgram": "Aperture-priority AE",
  "ISO": 400,
  "SensitivityType": "Recommended Exposure Index",
  "RecommendedExposureIndex": 400,
  "ExifVersion": "0230",
  "DateTimeOriginal": "2025:10:23 12:21:28",
  "CreateDate": "2025:10:23 12:21:28",
  "OffsetTimeOriginal": "-07:00",
  "ShutterSpeedValue": "1/20",
  "ApertureValue": 7.1,
  "ExposureCompensation": "+1",
  "MeteringMode": "Center-weighted average",
  "LightSource": "Unknown",
  "Flash": "Off, Did not fire",
  "FocalLength": "17.5 mm",
  "SubSecTimeOriginal": 65,
  "SubSecTimeDigitized": 65,
  "ExifImageWidth": 4350,
  "ExifImageHeight": 2894,

* • Map to app schema:
  * ◦ date_taken → DateTimeImmutable from DateTimeOriginal or FileModifyDate fallback.
  * ◦ f_stop → float(FNumber).
  * ◦ shutter_speed → numeric seconds if possible; otherwise keep original string for display.
  * ◦ iso → int(ISOSpeedRatings).
  * ◦ gps_lat, gps_lng → floats.
  * ◦ camera → slugified "#{Make} #{Model}".
* • Suggested ExifTool flags per environment:
  * ◦ High-speed ingest: -j -n -fast -charset filename=UTF8
  * ◦ Max-compatibility: -j -G -charset filename=UTF8 (no -n if textual values preferred)

⠀--- PERFORMANCE & DEPLOYMENT NOTEs

* • Reuse ExifTool process: consider using an ExifTool daemon mode if available (e.g., exiftool -stay_open True -@ -) to avoid process startup cost — implement only if load tests show benefit and handle lifecycle carefully.
* • For containerized deployment, ensure exiftool binary is included in image and accessible.
* • Use batching and single-process extraction when more than ~8 files per request to reduce overhead.
* • For very large uploads, rate-limit user simultaneous uploads and queue background extraction tasks.

⠀--- ACCEPTANCE CRITERIA

1. 1 Running php artisan ingest:scan temp/path (or equivalent) creates ProxyImage records with preview_path and metadata JSON within 200ms median per file for typical RAW/JPEG fixtures (target depends on infra; document observed numbers).
2. 2 Embedded preview images extracted and render correctly in UI. If embedded preview missing, fallback preview is created.
3. 3 All core metadata fields (date_taken, f_stop, shutter_speed, iso, gps_lat, gps_lng, camera_make, camera_model) are present and correctly typed for >99% of fixtures in test suite.
4. 4 Jobs handle exiftool failures gracefully: ProxyImage marked metadata_error and job does not crash the worker process.
5. 5 Unit & integration tests pass; a performance report is added to the repo.

⠀--- DELIVERABLES

* • app/Services/ExifToolService.php (with tests)
* • Updated app/Services/MetadataExtractor.php to rely on ExifToolService (migration of functionality)
* • Updated IngestController upload flow to save preview and normalized metadata to ProxyImage
* • Updated IngestProcessor and ProcessImageIngestJob to consume normalized metadata
* • Documentation: docs/ingest/exiftool.md
* • Test fixtures and unit/integration tests
* • Optional: perf script tools/ingest_perf.php with README

⠀--- EDGE CASES & FALLBACKS

* • If exiftool is missing, MetadataExtractor should fallback to PHP exif functions for minimal fields (but log warning and mark records).
* • If ExifTool returns malformed JSON, retry once; if still malformed, capture raw output to S3/log store for debugging and mark ProxyImage metadata as error.
* • If preview extraction produces >8MB image, downscale to 2MP to avoid UI/transport issues.

⠀--- COMMUNICATION & PR INSTRUCTIONS

* • Produce an implementation branch feature/exiftool-ingest.
* • Provide a PR description with: summary, performance numbers, DB migrations, and a short migration plan (how to roll back).
* • Include demonstration steps for QA: sample curl commands, paths to fixtures, and how to run perf script.

⠀--- EXECUTION

* • Provide code changes (fully runnable) with tests.
* • Provide short follow-up checklist for manual QA.
* • When finished, produce a short postmortem listing trade-offs (e.g. using -fast vs full scan, daemon vs process-per-call).

⠀Implement now and output:

1. 1 a summary of file-by-file changes and reasons,
2. 2 any added config settings (example values),
3. 3 the ExifToolService interface and skeleton in PHP,
4. 4 at least one example unit test (PHPUnit) for ExifToolService::extractMetadata.
