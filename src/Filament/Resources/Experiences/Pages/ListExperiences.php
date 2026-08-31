<?php

namespace CharlesStOlive\FilamentOrchestrator\Filament\Resources\Experiences\Pages;

use CharlesStOlive\FilamentOrchestrator\Filament\Resources\Experiences\ExperienceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExperiences extends ListRecords
{
    protected static string $resource = ExperienceResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
