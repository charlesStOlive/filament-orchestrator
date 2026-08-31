<?php

namespace CharlesStOlive\FilamentOrchestrator\Filament\Resources\Contents\Pages;

use CharlesStOlive\FilamentOrchestrator\Filament\Resources\Contents\ContentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListContents extends ListRecords
{
    protected static string $resource = ContentResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
