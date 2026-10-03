<?php

namespace CharlesStOlive\FilamentOrchestrator\Integrations\MapScenes;

use CharlesStOlive\FilamentMap\Models\GeoPoint;
use CharlesStOlive\FilamentMap\Services\MapPayloadBuilder;
use CharlesStOlive\FilamentOrchestrator\Library\LibraryImages;
use CharlesStOlive\FilamentOrchestrator\Models\LibraryMedia;
use CharlesStOlive\FilamentOrchestrator\Models\Orchestration;
use CharlesStOlive\FilamentOrchestrator\Models\OrchestratorNode;

final class ScenePoints
{
    /**
     * La clé, dans la config d'un nœud point, des ensembles de tags dont il propose l'image à son marqueur (voir
     * NodeDeclaration::markerImage()).
     */
    public const MARKER_IMAGE_CONFIG_KEY = 'marker_image_tags';

    public function for(Orchestration $orchestration, OrchestratorNode $sceneNode): array
    {
        return $orchestration->nodes()->where('role', 'point')->where('is_active', true)
            ->with(['orchestratable'])->get()
            ->filter(fn ($node) => $node->orchestratable instanceof GeoPoint && $node->orchestratable->is_active)
            ->filter(fn ($node) => empty($node->config['mapKey']) || $node->config['mapKey'] === $sceneNode->key)
            ->map(function ($node) use ($orchestration): array {
                $point = $node->orchestratable;
                $point->loadMissing(['type.media', 'media']);

                return array_replace_recursive(app(MapPayloadBuilder::class)->point($point, $this->markerImage($orchestration, $node, $point)), [
                    'key' => $node->key,
                    'sortOrder' => $node->sort_order,
                    'tooltip' => $point->name,
                    'cluster' => ['enabled' => false],
                ]);
            })->values()->all();
    }

    /**
     * L'image que ce point propose à son marqueur : la vignette de la première image de la bibliothèque portant le
     * premier ensemble de tags qui en a une (une vidéo n'en est pas une). Lue à l'affichage, comme les images d'un
     * contenu : elle suit la bibliothèque sans resynchroniser. Rien n'est cherché quand le type du point n'en montre
     * pas : l'image n'y servirait pas.
     */
    private function markerImage(Orchestration $orchestration, OrchestratorNode $node, GeoPoint $point): ?string
    {
        $tagSets = (array) ($node->config[self::MARKER_IMAGE_CONFIG_KEY] ?? []);
        $type = $point->type;

        // method_exists : un filament-map d'avant les images proposées n'en montre aucune.
        if ($tagSets === [] || $type === null || ! method_exists($type, 'acceptsImage') || ! $type->acceptsImage()) {
            return null;
        }

        $library = app(LibraryImages::class);

        foreach ($tagSets as $tags) {
            $media = $library->tagged($orchestration, (array) $tags)->first(fn (LibraryMedia $media): bool => ! $media->isPlayable());

            if ($media !== null) {
                return $media->thumbUrl();
            }
        }

        return null;
    }
}
