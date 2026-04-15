<?php

namespace ProPhoto\Gallery\Filament\Resources\GalleryResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use ProPhoto\Gallery\Filament\GalleryPlugin;
use ProPhoto\Gallery\Filament\Resources\GalleryResource;
use ProPhoto\Gallery\Filament\Widgets\GalleryDownloadStatsWidget;
use ProPhoto\Gallery\Models\Gallery;

/**
 * EditGallery
 *
 * Allows editing gallery name, subject, type, and mode_config after creation.
 * type is always editable per spec (locked decisions Q8).
 *
 * Pending types are managed separately via the PendingTypeTemplateResource
 * and per-gallery toggle in a future gallery detail page (Sprint 5).
 */
class EditGallery extends EditRecord
{
    protected static string $resource = GalleryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            GalleryResource::makeDeliverAction(),
            GalleryResource::makeCompleteAction(),
            GalleryResource::makeArchiveAction(),
            GalleryResource::makeUnarchiveAction(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getFooterWidgets(): array
    {
        $widgets = [];

        try {
            if (GalleryPlugin::get()->hasDownloadStats()) {
                $widgets[] = GalleryDownloadStatsWidget::class;
            }
        } catch (\Throwable) {
            // Plugin not registered — skip widget
        }

        return $widgets;
    }

    protected function getFooterWidgetData(): array
    {
        return [
            'galleryId' => $this->record->getKey(),
        ];
    }

    /**
     * Re-build mode_config on save, same logic as CreateGallery.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
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

        unset($data['template_key'], $data['pending_type_ids']);

        return $data;
    }
}
