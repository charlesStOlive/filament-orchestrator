<?php

namespace CharlesStOlive\FilamentOrchestrator\Filament\Resources\Automations\Pages;

use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditAutomation extends EditRecord
{
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return static::getResource()::getDefinition($this->record);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return static::getResource()::updateRecord($record, $data);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
