<?php

namespace CharlesStOlive\FilamentOrchestrator\Integrations\MapScenes;

use CharlesStOlive\FilamentMap\Models\GeoPoint;
use CharlesStOlive\FilamentMap\Services\MapPayloadBuilder;
use CharlesStOlive\FilamentOrchestrator\Models\Orchestration;
use CharlesStOlive\FilamentOrchestrator\Models\OrchestratorNode;

final class ScenePoints
{
    public function for(Orchestration $orchestration, OrchestratorNode $sceneNode): array
    {
        return $orchestration->nodes()->where('role', 'point')->where('is_active', true)
            ->with(['orchestratable'])->get()
            ->filter(fn ($node) => $node->orchestratable instanceof GeoPoint && $node->orchestratable->is_active)
            ->filter(fn ($node) => empty($node->config['mapKey']) || $node->config['mapKey'] === $sceneNode->key)
            ->map(function ($node): array {
                $point = $node->orchestratable;
                $point->loadMissing(['type.media', 'media']);

                return array_replace_recursive(app(MapPayloadBuilder::class)->point($point), [
                    'key' => $node->key,
                    'sortOrder' => $node->sort_order,
                    'tooltip' => $point->name,
                    'cluster' => ['enabled' => false],
                ]);
            })->values()->all();
    }
}
