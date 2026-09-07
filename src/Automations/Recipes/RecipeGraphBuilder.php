<?php

namespace CharlesStOlive\FilamentOrchestrator\Automations\Recipes;

use CharlesStOlive\FilamentOrchestrator\Automations\Actions\ActionBlueprint;
use CharlesStOlive\FilamentOrchestrator\Automations\Actions\GraphBlueprint;
use CharlesStOlive\FilamentOrchestrator\Automations\Actions\TriggerBlueprint;

final class RecipeGraphBuilder
{
    public function __construct(private readonly RecipeValues $values) {}

    public function build(array $specifications, array $context): GraphBlueprint
    {
        $graph = GraphBlueprint::make();
        foreach ($specifications as $spec) {
            foreach ($this->values->contexts($spec, $context) as $local) {
                $trigger = TriggerBlueprint::make(
                    $this->values->resolve($spec['key'], $local),
                    $spec['event'],
                    $this->values->resolve($spec['name'] ?? $spec['key'], $local),
                )->order(($spec['order'] ?? 0) + ($local['index'] ?? 0))
                    ->when($this->values->resolve($spec['conditions'] ?? [], $local));
                if (isset($spec['source'])) {
                    [$role, $key] = $this->values->resolve($spec['source'], $local);
                    $trigger->source($role, $key);
                }
                foreach ($spec['actions'] ?? [] as $index => $action) {
                    if (isset($action['if']) && ! $this->values->resolve($action['if'], $local)) {
                        continue;
                    }
                    if (isset($action['unless']) && $this->values->resolve($action['unless'], $local)) {
                        continue;
                    }
                    $built = ActionBlueprint::make(
                        $this->values->resolve($action['key'], $local), $action['action'],
                        $this->values->resolve($action['name'] ?? $action['key'], $local),
                    )->parameters($this->values->resolve($action['parameters'] ?? [], $local))
                        ->order($action['order'] ?? ($index + 1) * 10)
                        ->onError($action['on_error'] ?? 'continue');
                    if (isset($action['target'])) {
                        [$role, $key] = $this->values->resolve($action['target'], $local);
                        $built->target($role, $key);
                    }
                    $trigger->actions($built);
                }
                $graph->triggers($trigger);
            }
        }

        return $graph;
    }
}
