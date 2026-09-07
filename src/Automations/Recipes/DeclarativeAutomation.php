<?php

namespace CharlesStOlive\FilamentOrchestrator\Automations\Recipes;

use CharlesStOlive\FilamentOrchestrator\Automations\AutomationContext;
use CharlesStOlive\FilamentOrchestrator\Automations\ManagedOrchestrationAutomation;
use CharlesStOlive\FilamentOrchestrator\Models\Orchestration;

class DeclarativeAutomation extends ManagedOrchestrationAutomation
{
    public function __construct(private readonly string $automationKey, private readonly array $recipe) {}

    public function key(): string
    {
        return $this->automationKey;
    }

    public function version(): int
    {
        return $this->recipe['version'] ?? 1;
    }

    public function schema(): string
    {
        return $this->recipe['schema'];
    }

    public function configuration(): array
    {
        return $this->recipe;
    }

    protected function initialState(array $definition): array
    {
        return $this->recipe['initial_state'] ?? [];
    }

    protected function normalizeDefinition(array $data, array $previousDefinition = []): array
    {
        return app(RecipeNormalizer::class)->normalize($this->recipe, $data, $previousDefinition);
    }

    protected function legacyDefinition(Orchestration $orchestration): array
    {
        return app(RecipeRecovery::class)->recover($orchestration, $this->recipe['legacy'] ?? []);
    }

    protected function synchronize(AutomationContext $context): void
    {
        app(RecipeSynchronizer::class)->synchronize($this->recipe, $context, $this->key());
    }
}
