<?php

namespace ProPhoto\Gallery\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use ProPhoto\Gallery\Filament\Resources\GalleryResource;
use ProPhoto\Gallery\Models\GalleryShare;

/**
 * Story 5.3 — Dashboard widget showing recent proofing submissions.
 *
 * Displays the 10 most recent client submissions across all galleries,
 * scoped to the current studio. Appears on the Filament dashboard so
 * the photographer sees new submissions immediately after login.
 */
class RecentSubmissionsWidget extends BaseWidget
{
    protected static ?string $heading = 'Recent Proofing Submissions';

    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                GalleryShare::query()
                    ->whereNotNull('submitted_at')
                    ->where('is_locked', true)
                    ->with('gallery')
                    ->latest('submitted_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('gallery.subject_name')
                    ->label('Gallery')
                    ->url(fn (GalleryShare $record): ?string =>
                        $record->gallery
                            ? GalleryResource::getUrl('edit', ['record' => $record->gallery])
                            : null
                    )
                    ->color('primary')
                    ->weight('medium')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('confirmed_email')
                    ->label('Submitted By')
                    ->icon('heroicon-o-user')
                    ->searchable(),

                Tables\Columns\TextColumn::make('submitted_at')
                    ->label('When')
                    ->since()
                    ->sortable()
                    ->description(fn (GalleryShare $record): string =>
                        $record->submitted_at->format('M j, Y g:i A')
                    ),

                Tables\Columns\TextColumn::make('approval_summary')
                    ->label('Selections')
                    ->state(function (GalleryShare $record): string {
                        $approved = $record->approvalStates()
                            ->whereIn('status', ['approved', 'approved_pending'])
                            ->count();
                        $total = $record->gallery?->image_count ?? 0;

                        return "{$approved} / {$total} approved";
                    })
                    ->icon('heroicon-o-check-circle')
                    ->color('success'),
            ])
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('No submissions yet')
            ->emptyStateDescription('Share a proofing gallery to get started — submissions will appear here as clients submit their selections.')
            ->emptyStateIcon('heroicon-o-inbox');
    }
}
