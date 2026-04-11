<?php

namespace ProPhoto\Ingest\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * BatchFileUpdateRequest
 *
 * Validates PATCH /api/ingest/sessions/{sessionId}/files/batch
 * Used for cull toggling and star rating on multiple files at once.
 *
 * Story 1b.4 — Sprint 3
 */
class BatchFileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids'              => ['required', 'array', 'min:1'],
            'ids.*'            => ['required', 'string', 'uuid'],
            'updates'          => ['required', 'array'],
            'updates.culled'   => ['sometimes', 'boolean'],
            'updates.rating'   => ['sometimes', 'integer', 'min:0', 'max:5'],
        ];
    }
}
