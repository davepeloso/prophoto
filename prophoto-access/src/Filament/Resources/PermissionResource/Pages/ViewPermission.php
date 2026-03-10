<?php

namespace ProPhoto\Access\Filament\Resources\PermissionResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use ProPhoto\Access\Filament\Resources\PermissionResource;

class ViewPermission extends ViewRecord
{
    protected static string $resource = PermissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
