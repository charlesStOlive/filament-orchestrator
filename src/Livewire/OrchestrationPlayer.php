<?php

namespace CharlesStOlive\FilamentOrchestrator\Livewire;

use CharlesStOlive\FilamentOrchestrator\Models\Orchestration;
use CharlesStOlive\FilamentOrchestrator\Services\OrchestrationPayloadBuilder;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class OrchestrationPlayer extends Component
{
    public int|string|null $orchestrationId = null;

    public bool $showMap = true;

    public string $mapHeight = 'h-[520px]';

    public function mount(Orchestration|int|string $orchestration, bool $showMap = true, string $mapHeight = 'h-[520px]'): void
    {
        $this->orchestrationId = $orchestration instanceof Orchestration ? $orchestration->getKey() : $orchestration;
        $this->showMap = $showMap;
        $this->mapHeight = $mapHeight;
    }

    public function render(OrchestrationPayloadBuilder $payloadBuilder): View
    {
        $orchestration = Orchestration::query()
            ->where('is_active', true)
            ->findOrFail($this->orchestrationId);
        $orchestration->loadMissing('nodes.orchestratable');
        $payload = $payloadBuilder->build($orchestration);
        $activeMapNodeIds = collect($payload['nodes'])
            ->where('role', 'map')
            ->pluck('id');

        return view('filament-orchestrator::livewire.orchestration-player', [
            'orchestration' => $orchestration,
            'payload' => $payload,
            'mapNodes' => $orchestration->nodes
                ->whereIn('id', $activeMapNodeIds)
                ->values(),
            'mapAvailable' => class_exists('CharlesStOlive\\FilamentMap\\Models\\MapScene'),
            'playerDomId' => 'filament-orchestrator-'.$this->getId(),
        ]);
    }
}
