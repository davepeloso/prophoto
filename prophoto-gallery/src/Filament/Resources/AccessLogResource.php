<?php

namespace ProPhoto\Gallery\Filament\Resources;

use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use ProPhoto\Gallery\Filament\Resources\AccessLogResource\Pages;
use ProPhoto\Gallery\Models\GalleryAccessLog;

/**
 * Story 3.5 — Access Log Filament resource.
 *
 * Read-only table for browsing gallery access logs across all galleries.
 * Filterable by gallery, action type, and date range.
 */
class AccessLogResource extends Resource
{
    protected static ?string $model = GalleryAccessLog::class;

    protected static string|\BackedEnum|null $navigationIcon  = 'heroicon-o-eye';
    protected static \UnitEnum|string|null $navigationGroup = 'Galleries';
    protected static ?string $navigationLabel = 'Access Logs';
    protected static ?int    $navigationSort  = 3;

    protected static ?string $modelLabel = 'Access Log';
    protected static ?string $pluralModelLabel = 'Access Logs';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),

                Tables\Columns\TextColumn::make('gallery.subject_name')
                    ->label('Gallery')
                    ->searchable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('action')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        GalleryAccessLog::ACTION_VIEW     => 'info',
                        GalleryAccessLog::ACTION_DOWNLOAD => 'success',
                        GalleryAccessLog::ACTION_SHARE    => 'primary',
                        GalleryAccessLog::ACTION_COMMENT  => 'warning',
                        GalleryAccessLog::ACTION_RATE     => 'warning',
                        default                           => 'gray',
                    }),

                Tables\Columns\TextColumn::make('resource_type')
                    ->label('Resource')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('user_agent')
                    ->label('User Agent')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('action')
                    ->options([
                        GalleryAccessLog::ACTION_VIEW     => 'View',
                        GalleryAccessLog::ACTION_DOWNLOAD => 'Download',
                        GalleryAccessLog::ACTION_SHARE    => 'Share',
                        GalleryAccessLog::ACTION_COMMENT  => 'Comment',
                        GalleryAccessLog::ACTION_RATE     => 'Rate',
                    ]),

                Tables\Filters\SelectFilter::make('gallery_id')
                    ->label('Gallery')
                    ->relationship('gallery', 'subject_name'),
            ])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccessLogs::route('/'),
        ];
    }
}
