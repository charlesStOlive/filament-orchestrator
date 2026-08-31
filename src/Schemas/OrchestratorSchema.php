<?php

namespace CharlesStOlive\FilamentOrchestrator\Schemas;

use CharlesStOlive\FilamentOrchestrator\Schemas\Definitions\ActionDefinition;
use CharlesStOlive\FilamentOrchestrator\Schemas\Definitions\EventDefinition;
use CharlesStOlive\FilamentOrchestrator\Schemas\Definitions\NodeDefinition;
use Illuminate\Support\Collection;

abstract class OrchestratorSchema
{
    abstract public function key(): string;

    abstract public function label(): string;

    public function description(): ?string
    {
        return null;
    }

    /** @return array<NodeDefinition> */
    abstract public function nodes(): array;

    /** @return array<EventDefinition> */
    abstract public function events(): array;

    /** @return array<ActionDefinition> */
    abstract public function actions(): array;

    public function node(string $role): ?NodeDefinition
    {
        return $this->nodeCollection()->get($role);
    }

    public function event(string $name): ?EventDefinition
    {
        return $this->eventCollection()->get($name);
    }

    public function action(string $name): ?ActionDefinition
    {
        return $this->actionCollection()->get($name);
    }

    public function nodeCollection(): Collection
    {
        return collect($this->nodes())->keyBy(fn (NodeDefinition $definition): string => $definition->role);
    }

    public function eventCollection(): Collection
    {
        return collect($this->events())->keyBy(fn (EventDefinition $definition): string => $definition->name);
    }

    public function actionCollection(): Collection
    {
        return collect($this->actions())->keyBy(fn (ActionDefinition $definition): string => $definition->name);
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key(),
            'label' => $this->label(),
            'description' => $this->description(),
            'nodes' => $this->nodeCollection()->map->toArray()->all(),
            'events' => $this->eventCollection()->map->toArray()->all(),
            'actions' => $this->actionCollection()->map->toArray()->all(),
        ];
    }
}
