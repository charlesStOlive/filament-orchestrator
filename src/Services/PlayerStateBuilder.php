<?php

namespace CharlesStOlive\FilamentOrchestrator\Services;

use CharlesStOlive\FilamentOrchestrator\Integrations\MapScenes\ScenePoints;
use CharlesStOlive\FilamentOrchestrator\Models\Orchestration;
use CharlesStOlive\FilamentOrchestrator\Models\OrchestratorNode;

/**
 * Tout ce que le lecteur public (OrchestrationPlayer) montre d'une orchestration, en données seulement : le payload du
 * moteur et, pour chaque scène cartographique active, sa scène, ses hotpoints et ses réglages. Rien n'y renvoie à un
 * modèle qu'il faudrait relire : une application peut le garder tel quel (une version publiée) et le rendre plus tard
 * au lecteur (voir Contracts\PlayerStateResolver), quoi qu'il soit arrivé entre-temps aux contenus et aux hotpoints.
 */
class PlayerStateBuilder
{
    public function __construct(private OrchestrationPayloadBuilder $payloadBuilder) {}

    /** @return array{payload: array<string, mixed>, maps: array<int, array<string, mixed>>} */
    public function build(Orchestration $orchestration): array
    {
        $orchestration->loadMissing('nodes.orchestratable');
        $payload = $this->payloadBuilder->build($orchestration);

        return [
            'payload' => $payload,
            'maps' => $this->maps($orchestration, collect($payload['nodes'])->where('role', 'map')->pluck('id')->all()),
        ];
    }

    /**
     * Les scènes que le lecteur affiche : `key` distingue chacune dans la page, `scene` est l'identifiant de la scène
     * (filament-map), `points` ses hotpoints tels qu'elle les dessine.
     *
     * @param  array<int, int>  $activeMapNodeIds
     * @return array<int, array{key: string, scene: int, points: array<int, array<string, mixed>>, options: array<string, mixed>}>
     */
    protected function maps(Orchestration $orchestration, array $activeMapNodeIds): array
    {
        if (! class_exists('CharlesStOlive\\FilamentMap\\Models\\MapScene') || ! config('filament-orchestrator.integrations.map_scenes', false)) {
            return [];
        }

        return $orchestration->nodes
            ->whereIn('id', $activeMapNodeIds)
            ->filter(fn (OrchestratorNode $node): bool => $node->orchestratable_id && $node->orchestratable instanceof \CharlesStOlive\FilamentMap\Models\MapScene)
            ->map(fn (OrchestratorNode $node): array => [
                'key' => (string) $node->getKey(),
                'scene' => (int) $node->orchestratable_id,
                'points' => app(ScenePoints::class)->for($orchestration, $node),
                'options' => $orchestration->config['map_overrides'] ?? [],
            ])
            ->values()
            ->all();
    }
}
