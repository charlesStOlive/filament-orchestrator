<?php

namespace CharlesStOlive\FilamentOrchestrator;

use CharlesStOlive\FilamentOrchestrator\Filament\Resources\Contents\ContentResource;
use CharlesStOlive\FilamentOrchestrator\Filament\Resources\Orchestrations\OrchestrationResource;
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

    public function register(Panel $panel): void
    {
        $resources = [];

        if (config('filament-orchestrator.resources.orchestrations', true)) {
            $resources[] = OrchestrationResource::class;
        }

        if (config('filament-orchestrator.resources.contents', true)) {
            $resources[] = ContentResource::class;
        }

        $panel->resources($resources);
    }

    public function boot(Panel $panel): void {}
}
