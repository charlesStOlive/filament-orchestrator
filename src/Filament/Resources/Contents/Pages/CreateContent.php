<?php

namespace CharlesStOlive\FilamentOrchestrator\Filament\Resources\Contents\Pages;

use CharlesStOlive\FilamentOrchestrator\Filament\Concerns\HasContextualReturnAction;
use CharlesStOlive\FilamentOrchestrator\Filament\Resources\Contents\ContentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateContent extends CreateRecord
{
    use HasContextualReturnAction;

    protected static string $resource = ContentResource::class;

    public function mount(): void
    {
        $this->captureContextualCreation();

        parent::mount();
    }

    protected function afterCreate(): void
    {
        $this->dispatchContextualResourceCreated($this->record);
    }

    protected function getRedirectUrl(): string
    {
        return $this->contextualRedirectUrl(parent::getRedirectUrl());
    }

    protected function getHeaderActions(): array
    {
        return array_filter([$this->contextualReturnAction()]);
    }
}
