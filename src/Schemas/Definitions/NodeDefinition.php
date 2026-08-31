<?php

namespace CharlesStOlive\FilamentOrchestrator\Schemas\Definitions;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class NodeDefinition
{
    /** @param class-string<Model> $model */
    public function __construct(
        public readonly string $role,
        public readonly string $label,
        public readonly string $model,
        public readonly bool $multiple = true,
        public readonly array $ownerships = ['linked'],
        public readonly string $defaultOwnership = 'linked',
    ) {
        if (! is_subclass_of($model, Model::class)) {
            throw new InvalidArgumentException("The model [{$model}] declared for [{$role}] is not an Eloquent model.");
        }

        if (! in_array($defaultOwnership, $ownerships, true)) {
            throw new InvalidArgumentException("Default ownership [{$defaultOwnership}] is not allowed for [{$role}].");
        }
    }

    /** @param class-string<Model> $model */
    public static function make(
        string $role,
        string $label,
        string $model,
        bool $multiple = true,
        array $ownerships = ['linked'],
        string $defaultOwnership = 'linked',
    ): self {
        return new self($role, $label, $model, $multiple, $ownerships, $defaultOwnership);
    }

    public function toArray(): array
    {
        return [
            'role' => $this->role,
            'label' => $this->label,
            'model' => $this->model,
            'multiple' => $this->multiple,
            'ownerships' => $this->ownerships,
            'defaultOwnership' => $this->defaultOwnership,
        ];
    }
}
