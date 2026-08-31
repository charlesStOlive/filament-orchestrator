<?php

namespace CharlesStOlive\FilamentOrchestrator\Filament\Resources\Contents\Pages;

use CharlesStOlive\FilamentOrchestrator\Filament\Concerns\HasContextualReturnAction;
use CharlesStOlive\FilamentOrchestrator\Filament\Resources\Contents\ContentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditContent extends EditRecord
{
    use HasContextualReturnAction;

    protected static string $resource = ContentResource::class;

    protected function getHeaderActions(): array
    {
        return array_filter([$this->contextualReturnAction(), DeleteAction::make()]);
    }
}
