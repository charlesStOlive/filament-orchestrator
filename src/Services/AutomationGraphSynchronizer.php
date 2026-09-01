<?php

namespace CharlesStOlive\FilamentOrchestrator\Services;

use CharlesStOlive\FilamentOrchestrator\Automations\Actions\GraphBlueprint;
use CharlesStOlive\FilamentOrchestrator\Models\Orchestration;

final class AutomationGraphSynchronizer
{
    public function replace(Orchestration $orchestration, GraphBlueprint $graph): void
    {
        $orchestration->triggers()->delete();

        foreach ($graph->toArray() as $triggerData) {
            $actions = $triggerData['actions'];
            unset($triggerData['actions']);

            $trigger = $orchestration->triggers()->create($triggerData);
            $trigger->actions()->createMany($actions);
        }
    }
}
