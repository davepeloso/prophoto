<?php

namespace ProPhoto\Gallery\Filament\Resources\GalleryResource\Actions;

use Filament\Forms\Components\CheckboxList;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Storage;
use ProPhoto\Assets\Models\Asset;
use ProPhoto\Assets\Models\AssetSessionContext;
use ProPhoto\Contracts\Contracts\Gallery\GalleryRepositoryContract;
use ProPhoto\Contracts\DTOs\AssetId;
use ProPhoto\Contracts\DTOs\GalleryId;
use ProPhoto\Gallery\Models\Gallery;
use ProPhoto\Gallery\Models\Image;

/**
 * Story 2.4 — "Add Images from Session" table action.
 *
 * Opens a modal with a checkbox grid of all ready assets linked to the
 * gallery's session via asset_session_contexts. Already-added assets
 * are excluded from the selectable list.
 *
 * Architecture:
 *   READ:  asset_session_contexts + assets + asset_derivatives (prophoto-assets, read-only)
 *   WRITE: images table via GalleryRepositoryContract::attachAsset() (prophoto-gallery)
 */
class AddImagesFromSessionAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->name('add_images_from_session')
            ->label('Add Images from Session')
            ->icon('heroicon-o-photo')
            ->color('primary')
            ->modalHeading('Select Images to Add')
            ->modalDescription('Choose images from the linked session. Already-added images are excluded.')
            ->modalSubmitActionLabel('Add Selected')
            ->visible(fn (Gallery $record): bool => $record->session_id !== null)
            ->form(fn (Gallery $record): array => [
                CheckboxList::make('asset_ids')
                    ->label('Session Images')
                    ->options(fn () => $this->buildAssetOptions($record))
                    ->descriptions(fn () => $this->buildAssetDescriptions($record))
                    ->columns(4)
                    ->bulkToggleable()
                    ->required()
                    ->validationMessages([
                        'required' => 'Select at least one image to add.',
                    ]),
            ])
            ->action(function (array $data, Gallery $record): void {
                $this->attachSelectedAssets($record, $data['asset_ids'] ?? []);
            })
            ->deselectRecordsAfterCompletion();
    }

    /**
     * Build the options array: asset_id => display label.
     *
     * Queries asset_session_contexts for the gallery's session_id,
     * loads those assets with their thumbnail derivatives,
     * then excludes any already linked to this gallery.
     */
    protected function buildAssetOptions(Gallery $record): array
    {
        $availableAssets = $this->getAvailableAssets($record);

        $options = [];
        foreach ($availableAssets as $asset) {
            $options[$asset->id] = $asset->original_filename;
        }

        return $options;
    }

    /**
     * Build description strings showing thumbnail + file size.
     */
    protected function buildAssetDescriptions(Gallery $record): array
    {
        $availableAssets = $this->getAvailableAssets($record);

        $descriptions = [];
        foreach ($availableAssets as $asset) {
            $size = $this->humanFileSize($asset->bytes ?? 0);
            $descriptions[$asset->id] = "{$asset->mime_type} · {$size}";
        }

        return $descriptions;
    }

    /**
     * Get assets linked to this gallery's session that aren't already in the gallery.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Asset>
     */
    protected function getAvailableAssets(Gallery $record): \Illuminate\Database\Eloquent\Collection
    {
        // Get asset IDs linked to this session via asset_session_contexts
        $sessionAssetIds = AssetSessionContext::where('session_id', $record->session_id)
            ->pluck('asset_id');

        if ($sessionAssetIds->isEmpty()) {
            return new \Illuminate\Database\Eloquent\Collection();
        }

        // Exclude assets already attached to this gallery
        $existingAssetIds = Image::where('gallery_id', $record->id)
            ->whereNotNull('asset_id')
            ->pluck('asset_id');

        return Asset::whereIn('id', $sessionAssetIds)
            ->whereNotIn('id', $existingAssetIds)
            ->where('status', 'ready')
            ->with('derivatives')
            ->orderBy('original_filename')
            ->limit(200)
            ->get();
    }

    /**
     * Attach selected assets to the gallery via the repository contract.
     */
    protected function attachSelectedAssets(Gallery $record, array $assetIds): void
    {
        /** @var GalleryRepositoryContract $repository */
        $repository = app(GalleryRepositoryContract::class);

        $galleryId = GalleryId::from($record->id);

        foreach ($assetIds as $assetId) {
            $repository->attachAsset($galleryId, AssetId::from((int) $assetId));
        }

        // Notification handled by Filament's default success notification
    }

    protected function humanFileSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 0) . ' KB';
        }
        return $bytes . ' B';
    }
}
