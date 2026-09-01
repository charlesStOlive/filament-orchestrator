<?php

namespace CharlesStOlive\FilamentOrchestrator\Automations\Actions;

final class ActionBlueprint
{
    private ?string $targetRole = null;

    private ?string $targetKey = null;

    private array $parameters = [];

    private int $sortOrder = 0;

    private string $onError = 'continue';

    private bool $active = true;

    private function __construct(
        public readonly string $key,
        public readonly string $action,
        public readonly ?string $name = null,
    ) {}

    public static function make(string $key, string $action, ?string $name = null): self
    {
        return new self($key, $action, $name);
    }

    public function target(string $role, string $key): self
    {
        $this->targetRole = $role;
        $this->targetKey = $key;

        return $this;
    }

    public function parameters(array $parameters): self
    {
        $this->parameters = $parameters;

        return $this;
    }

    public function order(int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function onError(string $strategy): self
    {
        $this->onError = $strategy;

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
            'action' => $this->action,
            'target_role' => $this->targetRole,
            'target_key' => $this->targetKey,
            'parameters' => $this->parameters,
            'sort_order' => $this->sortOrder,
            'on_error' => $this->onError,
            'is_active' => $this->active,
        ];
    }
}
