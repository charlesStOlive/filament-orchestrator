<?php

namespace CharlesStOlive\FilamentOrchestrator\Livewire;

use CharlesStOlive\FilamentOrchestrator\Models\Experience;
use CharlesStOlive\FilamentOrchestrator\Services\ExperiencePayloadBuilder;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ExperiencePlayer extends Component
{
    public int|string|null $experienceId = null;

    public bool $showMap = true;

    public string $mapHeight = 'h-[520px]';

    public function mount(Experience|int|string $experience, bool $showMap = true, string $mapHeight = 'h-[520px]'): void
    {
        $this->experienceId = $experience instanceof Experience ? $experience->getKey() : $experience;
        $this->showMap = $showMap;
        $this->mapHeight = $mapHeight;
    }

    public function render(ExperiencePayloadBuilder $payloadBuilder): View
    {
        $experience = Experience::query()->findOrFail($this->experienceId);

        return view('filament-orchestrator::livewire.experience-player', [
            'experience' => $experience,
            'payload' => $payloadBuilder->build($experience),
            'mapAvailable' => class_exists('CharlesStOlive\\FilamentMap\\Models\\Map'),
            'playerDomId' => 'filament-orchestrator-'.$this->getId(),
        ]);
    }
}
