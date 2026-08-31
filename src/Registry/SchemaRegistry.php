<?php

namespace CharlesStOlive\FilamentOrchestrator\Registry;

use CharlesStOlive\FilamentOrchestrator\Schemas\OrchestratorSchema;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class SchemaRegistry
{
    private ?Collection $schemas = null;

    public function __construct(private readonly Container $container) {}

    /** @return Collection<string, OrchestratorSchema> */
    public function all(): Collection
    {
        return $this->schemas ??= collect(config('filament-orchestrator.schemas', []))
            ->map(function (string|OrchestratorSchema $schema): OrchestratorSchema {
                $instance = is_string($schema) ? $this->container->make($schema) : $schema;

                if (! $instance instanceof OrchestratorSchema) {
                    throw new InvalidArgumentException('Every orchestrator schema must extend OrchestratorSchema.');
                }

                return $instance;
            })
            ->keyBy(fn (OrchestratorSchema $schema): string => $schema->key());
    }

    public function get(string $key): OrchestratorSchema
    {
        return $this->all()->get($key)
            ?? throw new InvalidArgumentException("Unknown orchestrator schema [{$key}].");
    }

    public function options(): array
    {
        return $this->all()->mapWithKeys(
            fn (OrchestratorSchema $schema): array => [$schema->key() => $schema->label()],
        )->all();
    }
}
