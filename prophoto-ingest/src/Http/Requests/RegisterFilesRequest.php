<?php

namespace ProPhoto\Ingest\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * RegisterFilesRequest
 *
 * Validates POST /api/ingest/sessions/{sessionId}/files
 *
 * Called by IngestGallery.tsx immediately after the page mounts.
 * Registers all files in the upload session so the backend assigns
 * UUIDs before the UploadManager starts transferring bytes.
 *
 * Story 1b.4 — Sprint 3
 */
class RegisterFilesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'files'                    => ['required', 'array', 'min:1', 'max:500'],
            'files.*.filename'         => ['required', 'string', 'max:500'],
            'files.*.file_size'        => ['required', 'integer', 'min:0'],
            'files.*.file_type'        => ['required', 'string', 'max:20'],
            'files.*.exif'             => ['sometimes', 'array'],
            'files.*.exif.timestamp'   => ['sometimes', 'nullable', 'string'],
            'files.*.exif.iso'         => ['sometimes', 'nullable', 'integer'],
            'files.*.exif.aperture'    => ['sometimes', 'nullable', 'numeric'],
            'files.*.exif.focalLength' => ['sometimes', 'nullable', 'integer'],
            'files.*.exif.camera'      => ['sometimes', 'nullable', 'string', 'max:200'],
            'files.*.exif.gps'         => ['sometimes', 'nullable', 'array'],
            'files.*.exif.gps.lat'     => ['required_with:files.*.exif.gps', 'numeric', 'between:-90,90'],
            'files.*.exif.gps.lng'     => ['required_with:files.*.exif.gps', 'numeric', 'between:-180,180'],
        ];
    }
}
