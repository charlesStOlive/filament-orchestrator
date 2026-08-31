<?php

namespace CharlesStOlive\FilamentOrchestrator\Contracts;

interface Orchestratable
{
    /**
     * Return public data that may safely be exposed to the browser runtime.
     */
    public function toOrchestratorPayload(): array;
}
