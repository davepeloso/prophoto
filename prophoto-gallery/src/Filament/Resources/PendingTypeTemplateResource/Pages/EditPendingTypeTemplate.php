<?php

namespace ProPhoto\Gallery\Filament\Resources\PendingTypeTemplateResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use ProPhoto\Gallery\Filament\Resources\PendingTypeTemplateResource;

class EditPendingTypeTemplate extends EditRecord
{
    protected static string $resource = PendingTypeTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->hidden(fn () => $this->record?->is_system_default)
                ->requiresConfirmation(),
        ];
    }
}
