<?php

namespace ProPhoto\Gallery\Filament\Resources\PendingTypeTemplateResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use ProPhoto\Gallery\Filament\Resources\PendingTypeTemplateResource;

class ListPendingTypeTemplates extends ListRecords
{
    protected static string $resource = PendingTypeTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add Pending Type'),
        ];
    }
}
