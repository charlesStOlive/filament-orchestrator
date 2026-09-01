<?php

namespace CharlesStOlive\FilamentOrchestrator\Automations;

use CharlesStOlive\FilamentOrchestrator\Contracts\ManagedOrchestrationAutomation as ManagedOrchestrationAutomationContract;
use CharlesStOlive\FilamentOrchestrator\Models\Orchestration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

abstract class ManagedOrchestrationAutomation implements ManagedOrchestrationAutomationContract
{
    public function version(): int
    {
        return 1;
    }

    final public function create(array $data): Orchestration
    {
        return DB::transaction(function () use ($data): Orchestration {
            $definition = $this->normalizeDefinition($data);
            $orchestration = Orchestration::query()->create($this->creationAttributes($definition));
            $this->persistDefinition($orchestration, $definition);
            $this->synchronize(new AutomationContext($orchestration, $definition, [], true));

            return $orchestration->refresh();
        });
    }

    final public function update(Orchestration $orchestration, array $data): Orchestration
    {
        return DB::transaction(function () use ($orchestration, $data): Orchestration {
            $this->ensureManaged($orchestration);
            $previousDefinition = $this->definition($orchestration);
            $definition = $this->normalizeDefinition($data, $previousDefinition);
            $orchestration->update($this->updateAttributes($definition, $orchestration));
            $this->persistDefinition($orchestration, $definition);
            $this->synchronize(new AutomationContext(
                $orchestration->refresh(),
                $definition,
                $previousDefinition,
                false,
            ));

            return $orchestration->refresh();
        });
    }

    public function definition(Orchestration $orchestration): array
    {
        $definition = $orchestration->config['automation_definition'] ?? null;

        return is_array($definition) ? $definition : $this->legacyDefinition($orchestration);
    }

    protected function creationAttributes(array $definition): array
    {
        $name = (string) ($definition['name'] ?? 'Orchestration');

        return [
            'schema' => $this->schema(),
            'name' => $name,
            'key' => $this->uniqueKey(Str::slug($name) ?: $this->key()),
            'description' => $definition['description'] ?? null,
            'initial_state' => $this->initialState($definition),
            'is_active' => true,
        ];
    }

    protected function updateAttributes(array $definition, Orchestration $orchestration): array
    {
        return [
            'name' => $definition['name'] ?? $orchestration->name,
            'description' => $definition['description'] ?? null,
        ];
    }

    protected function initialState(array $definition): array
    {
        return [];
    }

    protected function legacyDefinition(Orchestration $orchestration): array
    {
        return [];
    }

    abstract protected function normalizeDefinition(array $data, array $previousDefinition = []): array;

    abstract protected function synchronize(AutomationContext $context): void;

    private function persistDefinition(Orchestration $orchestration, array $definition): void
    {
        $orchestration->update([
            'event_scope' => $orchestration->event_scope ?: $orchestration->key,
            'config' => [
                ...($orchestration->config ?? []),
                'automation' => $this->key(),
                'automation_version' => $this->version(),
                'automation_definition' => $definition,
            ],
        ]);
    }

    private function ensureManaged(Orchestration $orchestration): void
    {
        if ($orchestration->schema !== $this->schema() || ($orchestration->config['automation'] ?? null) !== $this->key()) {
            throw ValidationException::withMessages([
                'orchestration' => "Cette orchestration n’est pas gérée par l’automatisation [{$this->key()}].",
            ]);
        }
    }

    private function uniqueKey(string $base): string
    {
        $candidate = $base;
        $suffix = 2;

        while (Orchestration::query()->where('key', $candidate)->exists()) {
            $candidate = $base.'-'.$suffix++;
        }

        return $candidate;
    }
}
