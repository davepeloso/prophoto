<?php

namespace ProPhoto\Ingest\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * UploadFileRequest
 *
 * Validates POST /api/ingest/sessions/{sessionId}/upload
 * (the per-file binary upload from UploadManager.ts via XHR)
 *
 * Story 1b.4 — Sprint 3
 */
class UploadFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Max 200MB per file — covers large RAW files (Canon R5 = ~90MB)
        return [
            'file'    => ['required', 'file', 'max:204800'],
            'file_id' => ['required', 'string', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.max' => 'File exceeds the 200MB per-file upload limit.',
        ];
    }
}
