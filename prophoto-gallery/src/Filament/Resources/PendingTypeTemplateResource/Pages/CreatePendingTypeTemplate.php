<?php

namespace ProPhoto\Gallery\Filament\Resources\PendingTypeTemplateResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use ProPhoto\Gallery\Filament\Resources\PendingTypeTemplateResource;

class CreatePendingTypeTemplate extends CreateRecord
{
    protected static string $resource = PendingTypeTemplateResource::class;

    /**
     * Automatically scope new templates to the logged-in user's studio.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['studio_id']          = auth()->user()?->studio_id;
        $data['is_system_default']  = false;

        return $data;
    }
}
