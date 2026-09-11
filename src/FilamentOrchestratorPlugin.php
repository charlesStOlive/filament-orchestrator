<?php

namespace CharlesStOlive\FilamentOrchestrator;

use CharlesStOlive\FilamentOrchestrator\Filament\Resources\Contents\ContentResource;
use CharlesStOlive\FilamentOrchestrator\Filament\Resources\Orchestrations\OrchestrationResource;
use CharlesStOlive\FilamentOrchestrator\Registry\AutomationRegistry;
use Filament\Contracts\Plugin;
use Filament\Panel;

class FilamentOrchestratorPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'filament-orchestrator';
    }

    public function cluster(?string $cluster): static
    {
        config()->set('filament-orchestrator.cluster.enabled', $cluster !== null);
        config()->set('filament-orchestrator.cluster.class', $cluster);

        return $this;
    }

    public function nodeManagement(array $configuration): static
    {
        config()->set('filament-orchestrator.node_management', $configuration);

        return $this;
    }

    public function register(Panel $panel): void
    {
        $resources = [];

        if (config('filament-orchestrator.resources.orchestrations', true)) {
            $resources[] = OrchestrationResource::class;
        }

        if (config('filament-orchestrator.resources.contents', true)) {
            $resources[] = ContentResource::class;
        }

        // Les automatisations sont des ressources Filament à part entière :
        // la configuration ne fait que les désigner.
        $panel->resources([...$resources, ...app(AutomationRegistry::class)->resources()]);
    }

    public function boot(Panel $panel): void {}
}
