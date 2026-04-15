<?php

namespace ProPhoto\Gallery\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use ProPhoto\Gallery\Models\Gallery;
use ProPhoto\Gallery\Models\GalleryShare;

/**
 * Story 6.5 — Download stats widget for the gallery edit page.
 *
 * Shows per-share download breakdown on the gallery edit page so the
 * photographer can see who downloaded what and how many times.
 *
 * Registered on EditGallery via getFooterWidgets().
 * Scoped to the current gallery record.
 */
class GalleryDownloadStatsWidget extends BaseWidget
{
    protected static ?string $heading = 'Download Activity';

    protected int | string | array $columnSpan = 'full';

    protected static bool $isLazy = false;

    /**
     * The gallery record passed from the edit page.
     */
    public ?int $galleryId = null;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                GalleryShare::query()
                    ->when($this->galleryId, fn (Builder $q) =>
                        $q->where('gallery_id', $this->galleryId)
                    )
                    ->where('download_count', '>', 0)
                    ->latest('last_accessed_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('confirmed_email')
                    ->label('Client')
                    ->default(fn (GalleryShare $record): string =>
                        $record->confirmed_email ?? $record->shared_with_email ?? '—'
                    )
                    ->icon('heroicon-o-user')
                    ->searchable(['confirmed_email', 'shared_with_email']),

                Tables\Columns\TextColumn::make('download_count')
                    ->label('Downloads')
                    ->sortable()
                    ->alignCenter()
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('last_accessed_at')
                    ->label('Last Activity')
                    ->since()
                    ->sortable()
                    ->description(fn (GalleryShare $record): ?string =>
                        $record->last_accessed_at?->format('M j, Y g:i A')
                    ),

                Tables\Columns\TextColumn::make('access_count')
                    ->label('Views')
                    ->sortable()
                    ->alignCenter()
                    ->icon('heroicon-o-eye')
                    ->color('gray'),
            ])
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->defaultSort('download_count', 'desc')
            ->emptyStateHeading('No downloads yet')
            ->emptyStateDescription('Downloads will appear here once clients start downloading images from this gallery.')
            ->emptyStateIcon('heroicon-o-arrow-down-tray');
    }

    /**
     * Description shown below the heading with aggregate stats.
     */
    public function getTableDescription(): ?string
    {
        if (! $this->galleryId) {
            return null;
        }

        $gallery = Gallery::find($this->galleryId);
        if (! $gallery || $gallery->download_count < 1) {
            return null;
        }

        $shareCount = GalleryShare::where('gallery_id', $this->galleryId)
            ->where('download_count', '>', 0)
            ->count();

        $topImages = DB::table('gallery_activity_log')
            ->where('gallery_id', $this->galleryId)
            ->where('action_type', 'download')
            ->select('image_id', DB::raw('COUNT(*) as dl_count'))
            ->groupBy('image_id')
            ->orderByDesc('dl_count')
            ->limit(3)
            ->get();

        $totalDownloads = $gallery->download_count;
        $parts = ["{$totalDownloads} total " . ($totalDownloads === 1 ? 'download' : 'downloads')];
        $parts[] = "{$shareCount} " . ($shareCount === 1 ? 'client' : 'clients');

        if ($topImages->isNotEmpty()) {
            $uniqueImages = DB::table('gallery_activity_log')
                ->where('gallery_id', $this->galleryId)
                ->where('action_type', 'download')
                ->distinct('image_id')
                ->count('image_id');
            $parts[] = "{$uniqueImages} unique " . ($uniqueImages === 1 ? 'image' : 'images');
        }

        return implode(' · ', $parts);
    }
}
