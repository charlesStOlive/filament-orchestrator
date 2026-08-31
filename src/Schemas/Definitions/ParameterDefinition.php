<?php

namespace CharlesStOlive\FilamentOrchestrator\Schemas\Definitions;

use InvalidArgumentException;

final class ParameterDefinition
{
    public function __construct(
        public readonly string $key,
        public readonly string $type = 'string',
        public readonly ?string $label = null,
        public readonly bool $required = false,
        public readonly mixed $default = null,
        public readonly array $options = [],
        public readonly ?string $help = null,
    ) {
        if ($key === '') {
            throw new InvalidArgumentException('An action parameter requires a key.');
        }
    }

    public static function make(string $key, string $type = 'string'): self
    {
        return new self($key, $type);
    }

    public static function string(string $key, ?string $label = null, bool $required = false): self
    {
        return new self($key, 'string', $label, $required);
    }

    public static function number(string $key, ?string $label = null, bool $required = false): self
    {
        return new self($key, 'number', $label, $required);
    }

    public static function boolean(string $key, ?string $label = null, bool $required = false, bool $default = false): self
    {
        return new self($key, 'boolean', $label, $required, $default);
    }

    public static function list(string $key, ?string $label = null, bool $required = false): self
    {
        return new self($key, 'list', $label, $required);
    }

    public static function object(string $key, ?string $label = null, bool $required = false): self
    {
        return new self($key, 'object', $label, $required);
    }

    public static function select(string $key, array $options, ?string $label = null, bool $required = false): self
    {
        return new self($key, 'select', $label, $required, null, $options);
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'type' => $this->type,
            'label' => $this->label ?? $this->key,
            'required' => $this->required,
            'default' => $this->default,
            'options' => $this->options,
            'help' => $this->help,
        ];
    }
}
