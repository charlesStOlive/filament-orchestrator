<?php

namespace CharlesStOlive\FilamentOrchestrator\Filament\Resources\Automations\Pages;

use CharlesStOlive\FilamentOrchestrator\Registry\AutomationRegistry;
use Filament\Resources\Pages\Concerns\HasWizard;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAutomation extends CreateRecord
{
    use HasWizard;

    protected static bool $canCreateAnother = false;

    public function getSteps(): array
    {
        return static::getResource()::creationSteps();
    }

    protected function handleRecordCreation(array $data): Model
    {
        return app(AutomationRegistry::class)
            ->getManaged(static::getResource()::automationKey())
            ->create($data);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
