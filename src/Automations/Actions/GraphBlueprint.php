<?php

namespace CharlesStOlive\FilamentOrchestrator\Automations\Actions;

use InvalidArgumentException;

final class GraphBlueprint
{
    private array $triggers = [];

    public static function make(): self
    {
        return new self;
    }

    public function triggers(TriggerBlueprint ...$triggers): self
    {
        foreach ($triggers as $trigger) {
            if (isset($this->triggers[$trigger->key])) {
                throw new InvalidArgumentException("Duplicate trigger key [{$trigger->key}].");
            }

            $this->triggers[$trigger->key] = $trigger;
        }

        return $this;
    }

    public function toArray(): array
    {
        return array_map(
            static fn (TriggerBlueprint $trigger): array => $trigger->toArray(),
            array_values($this->triggers),
        );
    }
}
