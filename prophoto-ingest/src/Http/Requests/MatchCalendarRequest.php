<?php

namespace ProPhoto\Ingest\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * MatchCalendarRequest
 *
 * Validates the POST /api/ingest/match-calendar payload.
 *
 * The frontend sends:
 *   - metadata[]     — array of image metadata objects (from MetadataExtractor.ts)
 *   - studio_id      — the active studio ID
 *   - user_id        — the authenticated user's ID
 *
 * Each metadata item shape (from MetadataExtractor.ts):
 *   {
 *     filename:  string,
 *     fileSize:  int,
 *     fileType:  string,
 *     exif: {
 *       timestamp?: string,       // ISO 8601
 *       gps?: { lat: float, lng: float },
 *       iso?: int,
 *       aperture?: float,
 *       focalLength?: int,
 *       camera?: string,
 *     }
 *   }
 *
 * Story 1a.4 — Sprint 2
 */
class MatchCalendarRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Auth is handled by the api middleware; session ownership will be
        // validated at the service layer. Allow all authenticated users here.
        return true;
    }

    public function rules(): array
    {
        return [
            'studio_id'                        => ['required', 'integer', 'min:1'],
            'user_id'                          => ['required', 'integer', 'min:1'],

            // At least 1 image required; max 500 per batch request
            'metadata'                         => ['required', 'array', 'min:1', 'max:500'],
            'metadata.*.filename'              => ['required', 'string', 'max:500'],
            'metadata.*.fileSize'              => ['required', 'integer', 'min:0'],
            'metadata.*.fileType'              => ['required', 'string', 'in:raw,jpg,jpeg,heic,tiff,dng,png'],
            'metadata.*.exif'                  => ['sometimes', 'array'],
            'metadata.*.exif.timestamp'        => ['sometimes', 'nullable', 'string'],
            'metadata.*.exif.iso'              => ['sometimes', 'nullable', 'integer'],
            'metadata.*.exif.aperture'         => ['sometimes', 'nullable', 'numeric'],
            'metadata.*.exif.focalLength'      => ['sometimes', 'nullable', 'integer'],
            'metadata.*.exif.camera'           => ['sometimes', 'nullable', 'string', 'max:200'],
            'metadata.*.exif.gps'              => ['sometimes', 'nullable', 'array'],
            'metadata.*.exif.gps.lat'          => ['required_with:metadata.*.exif.gps', 'numeric', 'between:-90,90'],
            'metadata.*.exif.gps.lng'          => ['required_with:metadata.*.exif.gps', 'numeric', 'between:-180,180'],
        ];
    }

    public function messages(): array
    {
        return [
            'metadata.required'   => 'At least one image metadata object is required.',
            'metadata.min'        => 'At least one image metadata object is required.',
            'metadata.max'        => 'Maximum 500 images per calendar match request.',
            'studio_id.required'  => 'A studio ID is required to create an upload session.',
        ];
    }
}
