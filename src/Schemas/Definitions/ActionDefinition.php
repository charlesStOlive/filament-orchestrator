<?php

namespace CharlesStOlive\FilamentOrchestrator\Schemas\Definitions;

use InvalidArgumentException;

final class ActionDefinition
{
    /** @param array<ParameterDefinition> $parameters */
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly ?string $targetRole = null,
        public readonly array $parameters = [],
        public readonly ?string $clientHandler = null,
    ) {
        foreach ($parameters as $parameter) {
            if (! $parameter instanceof ParameterDefinition) {
                throw new InvalidArgumentException("Action [{$name}] contains an invalid parameter definition.");
            }
        }
    }

    /** @param array<ParameterDefinition> $parameters */
    public static function make(
        string $name,
        string $label,
        ?string $targetRole = null,
        array $parameters = [],
        ?string $clientHandler = null,
    ): self {
        return new self($name, $label, $targetRole, $parameters, $clientHandler ?? $name);
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'targetRole' => $this->targetRole,
            'clientHandler' => $this->clientHandler ?? $this->name,
            'parameters' => array_map(
                static fn (ParameterDefinition $parameter): array => $parameter->toArray(),
                $this->parameters,
            ),
        ];
    }
}
