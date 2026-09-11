<?php

namespace CharlesStOlive\FilamentOrchestrator\Registry;

use CharlesStOlive\FilamentOrchestrator\Filament\Resources\Automations\AutomationResource;
use CharlesStOlive\FilamentOrchestrator\Models\Orchestration;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Automatisations déclarées en configuration, indexées par leur clé.
 *
 * Une automatisation est une ressource Filament : la configuration ne liste
 * que des classes, tout le reste — formulaire, table, pages, projection — est
 * déclaré dans la classe elle-même.
 */
final class AutomationRegistry
{
    /** @var Collection<string, class-string<AutomationResource>>|null */
    private ?Collection $automations = null;

    /** @return Collection<string, class-string<AutomationResource>> */
    public function all(): Collection
    {
        return $this->automations ??= collect(config('filament-orchestrator.automations', []))
            ->map(function (string $resource): string {
                if (! is_subclass_of($resource, AutomationResource::class)) {
                    throw new InvalidArgumentException(
                        "L'automatisation [{$resource}] doit étendre AutomationResource.",
                    );
                }

                return $resource;
            })
            ->keyBy(fn (string $resource): string => $resource::getAutomationKey());
    }

    /** @return class-string<AutomationResource> */
    public function get(string $key): string
    {
        return $this->all()->get($key)
            ?? throw new InvalidArgumentException("Automatisation inconnue [{$key}].");
    }

    public function has(string $key): bool
    {
        return $this->all()->has($key);
    }

    /**
     * Ressource gérant une orchestration donnée, s'il y en a une.
     *
     * @return class-string<AutomationResource>|null
     */
    public function for(Orchestration $orchestration): ?string
    {
        $key = $orchestration->config['automation'] ?? null;

        return $key ? $this->all()->get($key) : null;
    }

    /** @return array<int, class-string<AutomationResource>> */
    public function resources(): array
    {
        return $this->all()->values()->all();
    }
}
