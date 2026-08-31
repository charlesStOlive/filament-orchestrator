<?php

namespace CharlesStOlive\FilamentOrchestrator;

use CharlesStOlive\FilamentOrchestrator\Livewire\OrchestrationPlayer;
use CharlesStOlive\FilamentOrchestrator\Models\OrchestratorAction;
use CharlesStOlive\FilamentOrchestrator\Models\OrchestratorNode;
use CharlesStOlive\FilamentOrchestrator\Models\OrchestratorTrigger;
use CharlesStOlive\FilamentOrchestrator\Registry\SchemaRegistry;
use CharlesStOlive\FilamentOrchestrator\Services\ActionDefinitionValidator;
use CharlesStOlive\FilamentOrchestrator\Services\NodeDefinitionValidator;
use CharlesStOlive\FilamentOrchestrator\Services\TriggerDefinitionValidator;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentOrchestratorServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-orchestrator')
            ->hasConfigFile('filament-orchestrator')
            ->hasViews('filament-orchestrator')
            ->hasMigration('create_filament_orchestrator_tables');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(SchemaRegistry::class);
    }

    public function packageBooted(): void
    {
        OrchestratorNode::saving(fn (OrchestratorNode $node) => app(NodeDefinitionValidator::class)->validate($node));
        OrchestratorTrigger::saving(fn (OrchestratorTrigger $trigger) => app(TriggerDefinitionValidator::class)->validate($trigger));
        OrchestratorAction::saving(fn (OrchestratorAction $action) => app(ActionDefinitionValidator::class)->validate($action));

        Livewire::component('filament-orchestrator-player', OrchestrationPlayer::class);

        $this->publishes([
            __DIR__.'/../resources/js' => public_path('vendor/filament-orchestrator'),
        ], 'filament-orchestrator-assets');
    }
}
