<?php

namespace CharlesStOlive\FilamentOrchestrator\Filament\Resources\Orchestrations\Pages;

use CharlesStOlive\FilamentOrchestrator\Filament\Resources\Orchestrations\OrchestrationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOrchestration extends EditRecord
{
    protected static string $resource = OrchestrationResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
