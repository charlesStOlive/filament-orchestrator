<?php

namespace CharlesStOlive\FilamentOrchestrator;

use CharlesStOlive\FilamentMap\Models\MapScene;
use CharlesStOlive\FilamentOrchestrator\Events\ContextualResourceCreated;
use CharlesStOlive\FilamentOrchestrator\Integrations\MapScenes\MapSceneIntegration;
use CharlesStOlive\FilamentOrchestrator\Library\Livewire\MediaLibraryTable;
use CharlesStOlive\FilamentOrchestrator\Library\Livewire\TagImagesPanel;
use CharlesStOlive\FilamentOrchestrator\Livewire\OrchestrationPlayer;
use CharlesStOlive\FilamentOrchestrator\Models\LibraryMedia;
use CharlesStOlive\FilamentOrchestrator\Models\LibraryTag;
use CharlesStOlive\FilamentOrchestrator\Models\OrchestratorAction;
use CharlesStOlive\FilamentOrchestrator\Models\OrchestratorNode;
use CharlesStOlive\FilamentOrchestrator\Models\OrchestratorTrigger;
use CharlesStOlive\FilamentOrchestrator\Registry\AutomationRegistry;
use CharlesStOlive\FilamentOrchestrator\Registry\SchemaRegistry;
use CharlesStOlive\FilamentOrchestrator\Services\ActionDefinitionValidator;
use CharlesStOlive\FilamentOrchestrator\Services\AttachContextualResource;
use CharlesStOlive\FilamentOrchestrator\Services\NodeDefinitionValidator;
use CharlesStOlive\FilamentOrchestrator\Services\TriggerDefinitionValidator;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Tags\Tag;

class FilamentOrchestratorServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-orchestrator')
            ->hasConfigFile('filament-orchestrator')
            ->hasViews('filament-orchestrator')
            ->hasMigrations([
                'create_filament_orchestrator_tables',
                'add_library_columns_to_media_table',
                'add_sort_to_taggables_table',
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(AutomationRegistry::class);
        $this->app->singleton(SchemaRegistry::class);
    }

    public function packageBooted(): void
    {
        $this->useLibraryModels();

        Event::listen(ContextualResourceCreated::class, function ($event): void {
            app(AttachContextualResource::class)->handle($event->record, $event->context);
        });
        OrchestratorNode::saving(fn (OrchestratorNode $node) => app(NodeDefinitionValidator::class)->validate($node));
        OrchestratorTrigger::saving(fn (OrchestratorTrigger $trigger) => app(TriggerDefinitionValidator::class)->validate($trigger));
        OrchestratorAction::saving(fn (OrchestratorAction $action) => app(ActionDefinitionValidator::class)->validate($action));

        if (config('filament-orchestrator.integrations.map_scenes', false) && class_exists(MapScene::class)) {
            app(MapSceneIntegration::class)->boot();
        }
        if ($hiddenRoles = config('filament-orchestrator.hidden_node_roles', [])) {
            OrchestratorNode::addGlobalScope('hidden-roles', fn ($query) => $query->whereNotIn('role', $hiddenRoles));
        }

        Livewire::component('filament-orchestrator-player', OrchestrationPlayer::class);
        Livewire::component('filament-orchestrator-media-library', MediaLibraryTable::class);
        Livewire::component('filament-orchestrator-tag-images', TagImagesPanel::class);

        $this->publishes([
            __DIR__.'/../resources/js' => public_path('vendor/filament-orchestrator'),
        ], 'filament-orchestrator-assets');

        $this->publishes([
            __DIR__.'/../docs/knowledge-base' => base_path('docs/knowledge-base/fr'),
        ], 'filament-orchestrator-docs');
    }

    /**
     * Substitue les modèles Media et Tag de la bibliothèque à ceux de Spatie,
     * tant que l'application n'a pas choisi les siens.
     */
    private function useLibraryModels(): void
    {
        if (! config('filament-orchestrator.library.register_models', true)) {
            return;
        }

        if (config('media-library.media_model') === Media::class) {
            config(['media-library.media_model' => LibraryMedia::class]);
        }

        if (config('tags.tag_model') === Tag::class) {
            config(['tags.tag_model' => LibraryTag::class]);
        }
    }
}
