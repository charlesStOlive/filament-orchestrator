<?php

namespace CharlesStOlive\FilamentOrchestrator\Filament\Resources\Automations\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Création pilotée par l'automatisation plutôt que par le modèle.
 *
 * Pour un parcours en étapes, la page de l'application ajoute le trait
 * HasWizard de Filament et déclare ses getSteps() : rien de spécifique au
 * moteur n'est nécessaire pour cela.
 */
class CreateAutomation extends CreateRecord
{
    protected static bool $canCreateAnother = false;

    protected function handleRecordCreation(array $data): Model
    {
        return static::getResource()::createRecord($data);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
