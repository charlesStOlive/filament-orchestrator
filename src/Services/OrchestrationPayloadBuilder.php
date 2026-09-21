<?php

namespace CharlesStOlive\FilamentOrchestrator\Services;

use CharlesStOlive\FilamentOrchestrator\Contracts\Orchestratable;
use CharlesStOlive\FilamentOrchestrator\Library\LibraryImages;
use CharlesStOlive\FilamentOrchestrator\Models\Orchestration;
use CharlesStOlive\FilamentOrchestrator\Models\OrchestratorAction;
use CharlesStOlive\FilamentOrchestrator\Models\OrchestratorNode;
use CharlesStOlive\FilamentOrchestrator\Models\OrchestratorTrigger;
use Illuminate\Database\Eloquent\Model;

class OrchestrationPayloadBuilder
{
    public function build(Orchestration $orchestration): array
    {
        $orchestration->loadMissing([
            'nodes.orchestratable',
            'triggers.sourceNode',
            'triggers.actions.targetNode.orchestratable',
        ]);

        $schema = $orchestration->schemaDefinition();
        $library = new LibraryImages;
        $nodes = $orchestration->nodes
            ->where('is_active', true)
            ->filter(fn (OrchestratorNode $node): bool => $this->modelIsActive($node->orchestratable))
            ->values();

        return [
            'orchestration' => [
                'id' => $orchestration->getKey(),
                'key' => $orchestration->key,
                'schema' => $orchestration->schema,
                'scope' => $orchestration->scope(),
                'config' => $orchestration->config ?? [],
                'initialState' => $orchestration->initial_state ?? [],
            ],
            'schema' => $schema->toArray(),
            'nodes' => $nodes
                ->map(fn (OrchestratorNode $node): array => $this->node($node, $orchestration, $library))
                ->all(),
            'triggers' => $orchestration->triggers
                ->where('is_active', true)
                ->values()
                ->map(fn (OrchestratorTrigger $trigger): array => [
                    'id' => $trigger->getKey(),
                    'key' => $trigger->key,
                    'event' => $trigger->event,
                    'source' => [
                        'nodeId' => $trigger->source_node_id,
                        'role' => $trigger->source_role,
                        'key' => $trigger->source_key,
                    ],
                    'conditions' => $trigger->conditions ?? [],
                    'actions' => $trigger->actions
                        ->where('is_active', true)
                        ->values()
                        ->map(fn (OrchestratorAction $action): array => $this->action($action, $schema->action($action->action)?->clientHandler))
                        ->all(),
                ])
                ->all(),
        ];
    }

    protected function node(OrchestratorNode $node, Orchestration $orchestration, LibraryImages $library): array
    {
        $model = $node->orchestratable;
        $data = $this->modelPayload($model);
        $tags = (array) ($node->config[LibraryImages::NODE_CONFIG_KEY] ?? []);

        // Les images propres au modèle passent d'abord, puis celles de la
        // bibliothèque portant les tags que ce nœud déclare.
        if ($tags !== []) {
            $media = $library->tagged($orchestration, $tags);
            $images = $media->filter(fn ($item): bool => $item->isImage())->values();
            $videos = $media->filter(fn ($item): bool => $item->isVideo())->values();

            // Deux listes : les vidéos n'ont ni vignette ni taille d'affichage, et se numérotent à part
            // (« image 2 », « vidéo 1 »).
            $data['images'] = [
                ...($data['images'] ?? []),
                ...$images->map(fn ($item, int $index): array => $library->payload($item, header: $index === 0))->all(),
            ];
            $data['videos'] = $videos->map(fn ($item): array => $library->payload($item))->all();
        }

        return [
            'id' => $node->getKey(),
            'role' => $node->role,
            'key' => $node->key,
            'ownership' => $node->ownership,
            'model' => $model ? [
                'type' => $model->getMorphClass(),
                'id' => $model->getKey(),
            ] : null,
            'data' => $data,
            'config' => $node->config ?? [],
        ];
    }

    protected function action(OrchestratorAction $action, ?string $handler): array
    {
        return [
            'id' => $action->getKey(),
            'key' => $action->key,
            'name' => $action->action,
            'handler' => $handler ?? $action->action,
            'targetNodeId' => $action->target_node_id,
            'target' => [
                'role' => $action->target_role,
                'key' => $action->target_key,
            ],
            'parameters' => $action->parameters ?? [],
            'onError' => $action->on_error,
        ];
    }

    protected function modelPayload(?Model $model): array
    {
        if ($model instanceof Orchestratable) {
            return $model->toOrchestratorPayload();
        }

        if ($model === null) {
            return [];
        }

        return array_filter([
            'name' => $model->getAttribute('name'),
            'key' => $model->getAttribute('key') ?? $model->getAttribute('slug'),
        ], static fn (mixed $value): bool => $value !== null);
    }

    protected function modelIsActive(?Model $model): bool
    {
        return $model !== null && (! array_key_exists('is_active', $model->getAttributes()) || (bool) $model->getAttribute('is_active'));
    }
}
