<?php

namespace CharlesStOlive\FilamentOrchestrator\Filament\Resources\Experiences\Pages;

use CharlesStOlive\FilamentOrchestrator\Filament\Resources\Experiences\ExperienceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditExperience extends EditRecord
{
    protected static string $resource = ExperienceResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
