<?php

namespace CharlesStOlive\FilamentOrchestrator\Automations\Actions;

use InvalidArgumentException;

final class TriggerBlueprint
{
    private ?string $sourceRole = null;

    private ?string $sourceKey = null;

    private array $conditions = [];

    private array $actions = [];

    private int $sortOrder = 0;

    private bool $active = true;

    private function __construct(
        public readonly string $key,
        public readonly string $event,
        public readonly string $name,
    ) {}

    public static function make(string $key, string $event, string $name): self
    {
        return new self($key, $event, $name);
    }

    public function source(string $role, string $key): self
    {
        $this->sourceRole = $role;
        $this->sourceKey = $key;

        return $this;
    }

    public function when(array $conditions): self
    {
        $this->conditions = $conditions;

        return $this;
    }

    public function actions(ActionBlueprint ...$actions): self
    {
        foreach ($actions as $action) {
            if (isset($this->actions[$action->key])) {
                throw new InvalidArgumentException("Duplicate action key [{$action->key}] in trigger [{$this->key}].");
            }

            $this->actions[$action->key] = $action;
        }

        return $this;
    }

    public function order(int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function active(bool $active = true): self
    {
        $this->active = $active;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'event' => $this->event,
            'source_role' => $this->sourceRole,
            'source_key' => $this->sourceKey,
            'conditions' => $this->conditions,
            'sort_order' => $this->sortOrder,
            'is_active' => $this->active,
            'actions' => array_map(
                static fn (ActionBlueprint $action): array => $action->toArray(),
                array_values($this->actions),
            ),
        ];
    }
}
