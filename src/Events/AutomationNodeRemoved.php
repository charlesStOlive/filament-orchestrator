<?php

namespace CharlesStOlive\FilamentOrchestrator\Events;

use CharlesStOlive\FilamentOrchestrator\Models\OrchestratorNode;
use Illuminate\Database\Eloquent\Model;

/**
 * Émis juste avant qu'une automatisation ne retire un nœud qu'elle gérait.
 *
 * Permet aux intégrations de nettoyer ce que le moteur ne connaît pas :
 * l'intégration cartographique s'en sert pour détacher un hotpoint de ses
 * cartes avant sa suppression.
 */
final readonly class AutomationNodeRemoved
{
    public function __construct(
        public OrchestratorNode $node,
        public ?Model $model,
        public string $automation,
    ) {}
}
