<?php

namespace CharlesStOlive\FilamentOrchestrator\Livewire;

use CharlesStOlive\FilamentOrchestrator\Contracts\PlayerStateResolver;
use CharlesStOlive\FilamentOrchestrator\Models\Orchestration;
use CharlesStOlive\FilamentOrchestrator\Services\PlayerStateBuilder;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Le lecteur public d'une orchestration : ses scènes cartographiques et le moteur qui y réagit. Il montre
 * l'orchestration telle qu'elle est, ou — avec `version` — l'état figé que l'application a rangé sous cette version
 * (voir Contracts\PlayerStateResolver).
 */
class OrchestrationPlayer extends Component
{
    public int|string|null $orchestrationId = null;

    /** La version figée à montrer, ou null pour l'orchestration telle qu'elle est. */
    #[Locked]
    public ?string $version = null;

    public bool $showMap = true;

    public string $mapHeight = 'h-[520px]';

    public function mount(Orchestration|int|string $orchestration, bool $showMap = true, string $mapHeight = 'h-[520px]', int|string|null $version = null): void
    {
        $this->orchestrationId = $orchestration instanceof Orchestration ? $orchestration->getKey() : $orchestration;
        $this->showMap = $showMap;
        $this->mapHeight = $mapHeight;
        $this->version = $version === null ? null : (string) $version;
    }

    public function render(PlayerStateBuilder $stateBuilder): View
    {
        $orchestration = Orchestration::query()
            ->where('is_active', true)
            ->findOrFail($this->orchestrationId);
        $state = $this->version === null ? $stateBuilder->build($orchestration) : $this->frozenState($orchestration);

        abort_if($state === null, 404);

        return view('filament-orchestrator::livewire.orchestration-player', [
            'orchestration' => $orchestration,
            'payload' => $state['payload'],
            'maps' => $state['maps'],
            'playerDomId' => 'filament-orchestrator-'.$this->getId(),
        ]);
    }

    /** @return array{payload: array<string, mixed>, maps: array<int, array<string, mixed>>}|null */
    private function frozenState(Orchestration $orchestration): ?array
    {
        $class = config('filament-orchestrator.player.state_resolver');
        $resolver = $class === null ? null : app($class);

        return $resolver instanceof PlayerStateResolver ? $resolver->state($orchestration, $this->version) : null;
    }
}
