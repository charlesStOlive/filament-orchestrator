<?php

namespace CharlesStOlive\FilamentOrchestrator\Filament\Concerns;

trait BelongsToConfiguredOrchestratorCluster
{
    public static function getCluster(): ?string
    {
        if (! config('filament-orchestrator.cluster.enabled', false)) {
            return null;
        }

        return config('filament-orchestrator.cluster.class');
    }
}
