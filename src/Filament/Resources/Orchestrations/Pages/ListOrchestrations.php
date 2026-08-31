<?php

namespace CharlesStOlive\FilamentOrchestrator\Filament\Resources\Orchestrations\Pages;

use CharlesStOlive\FilamentOrchestrator\Filament\Resources\Orchestrations\OrchestrationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOrchestrations extends ListRecords
{
    protected static string $resource = OrchestrationResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
