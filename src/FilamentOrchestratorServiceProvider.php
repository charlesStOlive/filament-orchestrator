<?php

namespace CharlesStOlive\FilamentOrchestrator;

use CharlesStOlive\FilamentOrchestrator\Livewire\ExperiencePlayer;
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

    public function packageBooted(): void
    {
        Livewire::component('filament-orchestrator-player', ExperiencePlayer::class);

        $this->publishes([
            __DIR__.'/../resources/js' => public_path('vendor/filament-orchestrator'),
        ], 'filament-orchestrator-assets');
    }
}
