<?php

namespace CharlesStOlive\FilamentOrchestrator\Integrations\MapScenes;

use CharlesStOlive\FilamentMap\Models\MapScene;
use CharlesStOlive\FilamentOrchestrator\Models\OrchestratorAction;
use CharlesStOlive\FilamentOrchestrator\Models\OrchestratorNode;
use Illuminate\Validation\ValidationException;

final class SceneScopeValidator
{
    public function action(OrchestratorAction $action): void
    {
        if (! in_array($action->action, ['map.layer.show', 'map.layer.hide', 'map.layer.toggle', 'map.highlightFeature'], true)) {
            return;
        }

        $orchestration = $action->trigger->orchestration;
        if (! is_a($orchestration->schemaDefinition()->node($action->target_role ?: 'map')?->model ?? '', MapScene::class, true)) {
            return;
        }

        $target = $action->target_node_id
            ? $orchestration->nodes()->find($action->target_node_id)
            : $orchestration->nodes()->where('role', $action->target_role)->where('key', $action->target_key)->first();
        $scene = $target?->orchestratable;
        $layerKey = $action->parameters['layerKey'] ?? null;

        if (! $scene instanceof MapScene || ! $scene->layers()->where('key', $layerKey)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['parameters.layerKey' => 'Choisissez une couche active de la scène ciblée.']);
        }
    }

    public function node(OrchestratorNode $node): void
    {
        if (! $node->orchestratable instanceof MapScene) {
            return;
        }

        if ($node->orchestration->nodes()->where('role', $node->role)
            ->where('orchestratable_type', $node->orchestratable_type)
            ->where('orchestratable_id', $node->orchestratable_id)
            ->when($node->exists, fn ($query) => $query->whereKeyNot($node->getKey()))->exists()) {
            throw ValidationException::withMessages(['orchestratable_id' => 'Cette scène est déjà utilisée dans ce scénario.']);
        }
    }
}
