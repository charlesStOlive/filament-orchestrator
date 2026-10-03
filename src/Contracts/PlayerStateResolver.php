<?php

namespace CharlesStOlive\FilamentOrchestrator\Contracts;

use CharlesStOlive\FilamentOrchestrator\Models\Orchestration;

/**
 * Retrouve l'état figé d'une orchestration sous une version : ce que le lecteur public (OrchestrationPlayer) montre
 * à la place de l'orchestration telle qu'elle est. C'est l'application qui fige et range ses versions (une publication,
 * par exemple) ; le package ne sait que les lire. La classe est désignée par `filament-orchestrator.player.state_resolver`.
 */
interface PlayerStateResolver
{
    /**
     * L'état que PlayerStateBuilder::build() avait rendu, ou null si cette version n'existe pas pour cette orchestration.
     *
     * @return array{payload: array<string, mixed>, maps: array<int, array<string, mixed>>}|null
     */
    public function state(Orchestration $orchestration, string $version): ?array;
}
