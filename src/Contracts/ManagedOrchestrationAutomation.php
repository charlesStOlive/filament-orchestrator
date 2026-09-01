<?php

namespace CharlesStOlive\FilamentOrchestrator\Contracts;

use CharlesStOlive\FilamentOrchestrator\Models\Orchestration;

interface ManagedOrchestrationAutomation extends OrchestrationAutomation
{
    public function key(): string;

    public function version(): int;

    public function schema(): string;

    public function definition(Orchestration $orchestration): array;
}
