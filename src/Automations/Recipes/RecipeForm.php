<?php

namespace CharlesStOlive\FilamentOrchestrator\Automations\Recipes;

final class RecipeForm
{
    public function build(array $specs): array
    {
        return array_map(function (array $spec) {
            $component = $spec['component']::make($spec['name']);
            foreach ($spec['methods'] ?? [] as $method => $arguments) {
                $component->{$method}(...$arguments);
            }
            if (isset($spec['children'])) {
                $component->schema($this->build($spec['children']));
            }
            if (isset($spec['options_model'])) {
                $options = $spec['options_model'];
                $component->options(function () use ($options): array {
                    $query = $options['model']::query()->where($options['where'] ?? []);
                    foreach ($options['relations'] ?? [] as $relation => $where) {
                        $query->whereHas($relation, fn ($related) => $related->where($where));
                    }

                    return $query->orderBy($options['label'])->pluck($options['label'], $options['value'] ?? 'id')->all();
                });
            }
            foreach ($spec['state_bindings'] ?? [] as $method => $path) {
                $component->{$method}(fn (callable $get) => $get($path));
            }
            if (isset($spec['visible_when_filled'])) {
                $path = $spec['visible_when_filled'];
                $component->visible(fn (callable $get): bool => filled($get($path)));
            }
            if (isset($spec['item_label'])) {
                $path = $spec['item_label'];
                $component->itemLabel(fn (array $state): ?string => data_get($state, $path));
            }
            if (isset($spec['summary'])) {
                $summary = $spec['summary'];
                $component->content(function (callable $get) use ($summary): string {
                    $variables = [];
                    foreach ($summary['values'] as $name => $source) {
                        $value = $get($source['field']);
                        $variables[$name] = match ($source['type'] ?? 'value') {
                            'count' => count($value ?? []),
                            'model' => $source['model']::query()->find($value)?->getAttribute($source['attribute']) ?? '',
                            default => $value,
                        };
                    }

                    return app(RecipeValues::class)->resolve($summary['template'], $variables);
                });
            }

            return $component;
        }, $specs);
    }
}
