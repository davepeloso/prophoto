<?php

namespace ProPhoto\Gallery\Filament\Resources\GalleryResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use ProPhoto\Gallery\Filament\Resources\GalleryResource;

class ListGalleries extends ListRecords
{
    protected static string $resource = GalleryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Gallery'),
        ];
    }
}
