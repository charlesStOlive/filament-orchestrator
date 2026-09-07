<?php

namespace CharlesStOlive\FilamentOrchestrator\Automations\Recipes;

use CharlesStOlive\FilamentOrchestrator\Models\Orchestration;

final class RecipeRecovery
{
    public function __construct(private readonly RecipeValues $values) {}

    public function recover(Orchestration $orchestration, array $spec): array
    {
        if (! $spec) {
            return [];
        }

        return $this->read($orchestration, $spec, ['orchestration' => $orchestration->toArray()]);
    }

    private function read(Orchestration $orchestration, array $spec, array $context): array
    {
        foreach ($spec['nodes'] ?? [] as $name => $source) {
            $node = $orchestration->nodes()->where('role', $source['role'])
                ->where('key', $this->values->resolve($source['key'], $context))->first();
            $model = $node?->orchestratable;
            $context['nodes'][$name] = [...($source['defaults'] ?? []), ...($model?->toArray() ?? [])];
            foreach ($source['media'] ?? [] as $field => $collection) {
                $context['nodes'][$name][$field] = $model && method_exists($model, 'getMedia')
                    ? $model->getMedia($collection)->map(fn ($media) => $media->getCustomProperty('source_path') ?: $media->getPathRelativeToRoot())->values()->all()
                    : [];
            }
        }
        $result = $this->values->resolve($spec['values'] ?? [], $context);
        foreach ($spec['collections'] ?? [] as $path => $collection) {
            $items = [];
            foreach ($this->values->contexts($collection, $context) as $local) {
                $items[] = $this->read($orchestration, $collection, $local);
            }
            data_set($result, $path, $items);
        }

        return $result;
    }
}
