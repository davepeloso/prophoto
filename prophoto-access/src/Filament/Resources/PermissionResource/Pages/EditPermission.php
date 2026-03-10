<?php

namespace ProPhoto\Access\Filament\Resources\PermissionResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use ProPhoto\Access\Filament\Resources\PermissionResource;

class EditPermission extends EditRecord
{
    protected static string $resource = PermissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
