<?php

namespace CharlesStOlive\FilamentOrchestrator\Contracts;

use CharlesStOlive\FilamentOrchestrator\Models\Orchestration;

interface OrchestrationAutomation
{
    public function create(array $data): Orchestration;

    public function update(Orchestration $orchestration, array $data): Orchestration;
}
