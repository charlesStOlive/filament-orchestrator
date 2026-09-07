<?php

namespace CharlesStOlive\FilamentOrchestrator\Integrations\MapScenes;

use CharlesStOlive\FilamentMap\Events\ContextualResourceCreated as MapResourceCreated;
use CharlesStOlive\FilamentMap\Models\MapScene;
use CharlesStOlive\FilamentOrchestrator\Models\Orchestration;
use CharlesStOlive\FilamentOrchestrator\Models\OrchestratorAction;
use CharlesStOlive\FilamentOrchestrator\Models\OrchestratorNode;
use CharlesStOlive\FilamentOrchestrator\Services\AttachContextualResource;
use CharlesStOlive\FilamentOrchestrator\Services\OrchestrationPayloadBuilder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Event;

final class MapSceneIntegration
{
    public function boot(): void
    {
        app()->bind(OrchestrationPayloadBuilder::class, SceneOrchestrationPayloadBuilder::class);
        OrchestratorAction::saving(fn ($action) => app(SceneScopeValidator::class)->action($action));
        OrchestratorNode::saving(fn ($node) => app(SceneScopeValidator::class)->node($node));
        TextInput::configureUsing(function ($field): void {
            if ($field->getName() !== 'parameters.layerKey') {
                return;
            }
            $field->datalist(function (Get $get, $livewire): array {
                if (! method_exists($livewire, 'getOwnerRecord')) {
                    return [];
                }
                $orchestration = $livewire->getOwnerRecord();
                if (! $orchestration instanceof Orchestration) {
                    return [];
                }
                $node = $get('target_node_id')
                    ? $orchestration->nodes()->find($get('target_node_id'))
                    : $orchestration->nodes()->where('role', 'map')->where('key', $get('target_key'))->first();
                $scene = $node?->orchestratable;

                return $scene instanceof MapScene
                    ? $scene->layers()->where('is_active', true)->pluck('key')->all() : [];
            })->helperText('Choisissez une clé parmi les couches de la scène ciblée. La scène se prépare dans Scènes cartographiques.');
        });
        Select::configureUsing(function ($field): void {
            if (in_array($field->getName(), ['target_node_id', 'target_role'], true)) {
                $field->live();
            }
        });
        Event::listen(MapResourceCreated::class, function (MapResourceCreated $event): void {
            app(AttachContextualResource::class)->handle($event->record, $event->context);
        });

    }
}
