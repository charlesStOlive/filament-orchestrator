<?php

namespace CharlesStOlive\FilamentOrchestrator\Filament\Resources\Automations\Pages;

use CharlesStOlive\FilamentOrchestrator\Registry\AutomationRegistry;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditAutomation extends EditRecord
{
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return app(AutomationRegistry::class)
            ->getManaged(static::getResource()::automationKey())
            ->definition($this->record);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(AutomationRegistry::class)
            ->getManaged(static::getResource()::automationKey())
            ->update($record, $data);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
