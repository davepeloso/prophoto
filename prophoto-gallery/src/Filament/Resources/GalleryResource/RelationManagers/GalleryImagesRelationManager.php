<?php

namespace ProPhoto\Gallery\Filament\Resources\GalleryResource\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use ProPhoto\Gallery\Filament\Resources\GalleryResource\Actions\AddImagesFromSessionAction;
use ProPhoto\Gallery\Models\Gallery;
use ProPhoto\Gallery\Models\Image;

/**
 * Story 2.5 — Gallery Image Management relation manager.
 *
 * Displayed on the EditGallery page. Provides:
 *  - Sortable image table with thumbnails
 *  - Drag-and-drop reorder (persisted to images.sort_order)
 *  - Remove image action (soft-delete, asset preserved)
 *  - Add more images from session (header action reusing Story 2.4)
 *
 * Architecture:
 *   All writes target the images table (prophoto-gallery).
 *   Asset records are read-only (thumbnails via eager-loaded relation).
 */
class GalleryImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    protected static ?string $title = 'Gallery Images';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-photo';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query) =>
                $query->with(['asset.derivatives'])->orderBy('sort_order')
            )
            ->columns([
                Tables\Columns\ImageColumn::make('thumbnail_url')
                    ->label('Thumb')
                    ->getStateUsing(fn (Image $record): ?string => $record->resolvedThumbnailUrl())
                    ->circular()
                    ->size(48),

                Tables\Columns\TextColumn::make('original_filename')
                    ->label('Filename')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('asset_status')
                    ->label('Link')
                    ->badge()
                    ->getStateUsing(fn (Image $record): string => $record->asset_id ? 'Linked' : 'Orphan')
                    ->color(fn (string $state): string => match ($state) {
                        'Linked' => 'success',
                        'Orphan' => 'warning',
                        default  => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Added')
                    ->dateTime('M j, Y')
                    ->sortable(),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->headerActions([
                $this->makeAddImagesAction(),
            ])
            ->actions([
                $this->makeRemoveImageAction(),
            ])
            ->bulkActions([
                $this->makeRemoveBulkAction(),
            ]);
    }

    /**
     * "Add Images from Session" header action.
     *
     * Reuses the AddImagesFromSessionAction from Story 2.4.
     * Visible only when the parent gallery has a session_id.
     */
    protected function makeAddImagesAction(): Action
    {
        return Action::make('add_images_from_session')
            ->label('Add Images from Session')
            ->icon('heroicon-o-plus-circle')
            ->color('primary')
            ->visible(fn (): bool => $this->getOwnerRecord()->session_id !== null)
            ->modalHeading('Select Images to Add')
            ->modalDescription('Choose images from the linked session. Already-added images are excluded.')
            ->modalSubmitActionLabel('Add Selected')
            ->form(function (): array {
                /** @var Gallery $gallery */
                $gallery = $this->getOwnerRecord();

                $options = $this->getAvailableSessionAssets($gallery);

                if (empty($options)) {
                    return [
                        \Filament\Schemas\Components\Placeholder::make('no_images')
                            ->label('')
                            ->content('All session images have already been added to this gallery.'),
                    ];
                }

                return [
                    Forms\Components\CheckboxList::make('asset_ids')
                        ->label('Session Images')
                        ->options($options)
                        ->columns(3)
                        ->bulkToggleable()
                        ->required(),
                ];
            })
            ->action(function (array $data): void {
                $assetIds = $data['asset_ids'] ?? [];
                if (empty($assetIds)) {
                    return;
                }

                /** @var Gallery $gallery */
                $gallery = $this->getOwnerRecord();

                /** @var \ProPhoto\Contracts\Contracts\Gallery\GalleryRepositoryContract $repo */
                $repo = app(\ProPhoto\Contracts\Contracts\Gallery\GalleryRepositoryContract::class);

                foreach ($assetIds as $assetId) {
                    $repo->attachAsset(
                        \ProPhoto\Contracts\DTOs\GalleryId::from($gallery->id),
                        \ProPhoto\Contracts\DTOs\AssetId::from((int) $assetId)
                    );
                }

                Notification::make()
                    ->title(count($assetIds) . ' image(s) added')
                    ->success()
                    ->send();
            });
    }

    /**
     * Remove image row action — soft-deletes Image, preserves Asset.
     */
    protected function makeRemoveImageAction(): Action
    {
        return Action::make('remove_image')
            ->label('Remove')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Remove Image')
            ->modalDescription('This will remove the image from the gallery. The original asset file is preserved.')
            ->action(function (Image $record): void {
                $gallery = $record->gallery;
                $record->delete(); // soft-delete
                $gallery->updateCounts();

                Notification::make()
                    ->title('Image removed')
                    ->success()
                    ->send();
            });
    }

    /**
     * Bulk remove action.
     */
    protected function makeRemoveBulkAction(): BulkAction
    {
        return BulkAction::make('remove_selected')
            ->label('Remove Selected')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Remove Selected Images')
            ->modalDescription('This will remove all selected images from the gallery. Original asset files are preserved.')
            ->action(function (\Illuminate\Database\Eloquent\Collection $records): void {
                $gallery = null;

                foreach ($records as $record) {
                    $gallery = $gallery ?? $record->gallery;
                    $record->delete();
                }

                $gallery?->updateCounts();

                Notification::make()
                    ->title($records->count() . ' image(s) removed')
                    ->success()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    /**
     * Get available session assets not yet in this gallery.
     *
     * @return array<int, string> asset_id => filename
     */
    protected function getAvailableSessionAssets(Gallery $gallery): array
    {
        $sessionAssetIds = \ProPhoto\Assets\Models\AssetSessionContext::where('session_id', $gallery->session_id)
            ->pluck('asset_id');

        if ($sessionAssetIds->isEmpty()) {
            return [];
        }

        $existingAssetIds = Image::where('gallery_id', $gallery->id)
            ->whereNotNull('asset_id')
            ->pluck('asset_id');

        return \ProPhoto\Assets\Models\Asset::whereIn('id', $sessionAssetIds)
            ->whereNotIn('id', $existingAssetIds)
            ->where('status', 'ready')
            ->orderBy('original_filename')
            ->limit(200)
            ->pluck('original_filename', 'id')
            ->all();
    }
}
