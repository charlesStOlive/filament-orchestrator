<?php

namespace CharlesStOlive\FilamentOrchestrator\Schemas\Definitions;

final class EventDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly ?string $sourceRole = null,
        public readonly array $payload = [],
    ) {}

    public static function make(string $name, string $label, ?string $sourceRole = null, array $payload = []): self
    {
        return new self($name, $label, $sourceRole, $payload);
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'sourceRole' => $this->sourceRole,
            'payload' => $this->payload,
        ];
    }
}
