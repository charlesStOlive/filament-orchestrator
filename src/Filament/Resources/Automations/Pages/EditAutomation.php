<?php

namespace CharlesStOlive\FilamentOrchestrator\Filament\Resources\Automations\Pages;

use CharlesStOlive\FilamentOrchestrator\Filament\Split\HasSidePane;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Une page d'automatisation peut accueillir un volet latéral à côté de son
 * formulaire (voir HasSidePane) : elle n'a qu'à déclarer `getSidePanes()`. Sans
 * volet déclaré, la page est celle de Filament, inchangée.
 */
class EditAutomation extends EditRecord
{
    use HasSidePane;

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
