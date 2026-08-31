<?php

namespace CharlesStOlive\FilamentOrchestrator;

use CharlesStOlive\FilamentOrchestrator\Filament\Resources\Experiences\ExperienceResource;
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
        if (config('filament-orchestrator.resources.experiences', true)) {
            $panel->resources([ExperienceResource::class]);
        }
    }

    public function boot(Panel $panel): void
    {
        // Runtime services are registered by the package service provider.
    }
}
