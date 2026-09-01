<?php

namespace CharlesStOlive\FilamentOrchestrator\Automations;

use CharlesStOlive\FilamentOrchestrator\Models\Orchestration;

final readonly class AutomationContext
{
    public function __construct(
        public Orchestration $orchestration,
        public array $definition,
        public array $previousDefinition,
        public bool $creating,
    ) {}
}
