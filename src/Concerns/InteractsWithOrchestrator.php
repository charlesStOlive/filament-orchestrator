<?php

namespace CharlesStOlive\FilamentOrchestrator\Concerns;

use CharlesStOlive\FilamentOrchestrator\Models\OrchestratorNode;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait InteractsWithOrchestrator
{
    public function orchestratorNodes(): MorphMany
    {
        return $this->morphMany(OrchestratorNode::class, 'orchestratable');
    }
}
