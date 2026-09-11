<?php

namespace CharlesStOlive\FilamentOrchestrator\Automations\Projection;

use CharlesStOlive\FilamentOrchestrator\Automations\Actions\GraphBlueprint;

/**
 * Résultat de l'évaluation d'une projection : la définition normalisée à
 * réafficher dans le formulaire, les nœuds à synchroniser et le graphe à poser.
 */
final readonly class CompiledProjection
{
    /** @param array<int, NodeDeclaration> $nodes */
    public function __construct(
        public array $definition,
        public array $nodes,
        public GraphBlueprint $graph,
        public array $config = [],
    ) {}
}
