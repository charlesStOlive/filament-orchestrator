<?php

namespace CharlesStOlive\FilamentOrchestrator\Filament\Resources\Contents\Pages;

use CharlesStOlive\FilamentOrchestrator\Filament\Resources\Contents\ContentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditContent extends EditRecord
{
    protected static string $resource = ContentResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
