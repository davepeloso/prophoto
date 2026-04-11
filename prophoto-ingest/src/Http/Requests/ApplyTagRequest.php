<?php

namespace ProPhoto\Ingest\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use ProPhoto\Ingest\Models\IngestImageTag;

/**
 * ApplyTagRequest
 *
 * Validates POST /api/ingest/sessions/{sessionId}/files/{fileId}/tags
 *
 * Story 1b.4 — Sprint 3
 */
class ApplyTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $validTypes = implode(',', [
            IngestImageTag::TYPE_METADATA,
            IngestImageTag::TYPE_CALENDAR,
            IngestImageTag::TYPE_USER,
        ]);

        return [
            'tag'      => ['required', 'string', 'max:200'],
            'tag_type' => ['required', 'string', "in:{$validTypes}"],
        ];
    }
}
