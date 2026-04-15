<?php

namespace ProPhoto\Gallery\Filament\Resources\GalleryResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use ProPhoto\Gallery\Filament\Resources\GalleryResource;
use ProPhoto\Gallery\Models\Gallery;
use ProPhoto\Gallery\Models\GalleryPendingType;
use ProPhoto\Gallery\Services\GalleryActivityLogger;

/**
 * CreateGallery
 *
 * Handles form mutation and post-create logic for gallery creation.
 *
 * Responsibilities:
 *   1. Inject studio_id / organization_id from authenticated user
 *   2. Build the mode_config array from individual form fields
 *      (presentation galleries get null, not an empty array)
 *   3. After creation, populate gallery_pending_types from the
 *      checked template IDs collected in the pending_type_ids map
 *
 * Architecture:
 *   - Writes only to galleries and gallery_pending_types (prophoto-gallery)
 *   - No file handling or asset operations
 */
class CreateGallery extends CreateRecord
{
    protected static string $resource = GalleryResource::class;

    /**
     * Mutate form data before the Gallery record is created.
     * Converts the flat form shape into the correct DB column shape.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();

        // Ownership
        $data['studio_id']       = $user?->studio_id;
        $data['organization_id'] = $user?->organization_id;

        // Build mode_config — null for presentation galleries
        if (($data['type'] ?? Gallery::TYPE_PROOFING) === Gallery::TYPE_PRESENTATION) {
            $data['mode_config'] = null;
        } else {
            $rawConfig = $data['mode_config'] ?? [];

            $data['mode_config'] = [
                'min_approvals'       => isset($rawConfig['min_approvals']) && $rawConfig['min_approvals'] !== ''
                                            ? (int) $rawConfig['min_approvals']
                                            : null,
                'max_approvals'       => isset($rawConfig['max_approvals']) && $rawConfig['max_approvals'] !== ''
                                            ? (int) $rawConfig['max_approvals']
                                            : null,
                'max_pending'         => isset($rawConfig['max_pending']) && $rawConfig['max_pending'] !== ''
                                            ? (int) $rawConfig['max_pending']
                                            : null,
                'ratings_enabled'     => (bool) ($rawConfig['ratings_enabled'] ?? true),
                'pipeline_sequential' => (bool) ($rawConfig['pipeline_sequential'] ?? true),
            ];
        }

        // Remove wizard-only fields that don't belong on the Gallery model
        unset($data['template_key'], $data['pending_type_ids']);

        return $data;
    }

    /**
     * After the Gallery record is created, populate gallery_pending_types
     * from the checked template IDs in the form's pending_type_ids map.
     *
     * pending_type_ids is a map of [ template_id => bool ] where true = include.
     * Only runs for proofing galleries.
     */
    protected function afterCreate(): void
    {
        $gallery = $this->record;

        // Log gallery creation to the activity ledger
        GalleryActivityLogger::log(
            gallery: $gallery,
            actionType: 'gallery_created',
            actorType: 'studio_user',
            actorEmail: auth()->user()?->email,
        );

        if (! $gallery->isProofing()) {
            return;
        }

        // Collect the IDs the photographer explicitly checked
        $formData        = $this->data;
        $pendingTypeIds  = $formData['pending_type_ids'] ?? [];

        $checkedIds = collect($pendingTypeIds)
            ->filter(fn ($checked) => (bool) $checked)
            ->keys()
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($checkedIds)) {
            // Nothing checked — fall back to full studio defaults
            GalleryPendingType::populateFromStudioTemplates($gallery);
            return;
        }

        // Populate only the checked template IDs, preserving sort_order
        GalleryPendingType::populateFromTemplateIds($gallery, $checkedIds);
    }
}
