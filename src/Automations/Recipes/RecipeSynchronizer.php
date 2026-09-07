<?php

namespace CharlesStOlive\FilamentOrchestrator\Automations\Recipes;

use CharlesStOlive\FilamentOrchestrator\Automations\AutomationContext;
use CharlesStOlive\FilamentOrchestrator\Services\AutomationGraphSynchronizer;
use CharlesStOlive\FilamentOrchestrator\Services\NodeManager;
use Illuminate\Validation\ValidationException;

final class RecipeSynchronizer
{
    public function __construct(
        private readonly NodeManager $nodes,
        private readonly RecipeValues $values,
        private readonly RecipeGraphBuilder $graphs,
        private readonly AutomationGraphSynchronizer $synchronizer,
        private readonly RecipeMedia $media,
    ) {}

    public function synchronize(array $recipe, AutomationContext $context, string $automation): void
    {
        $orchestration = $context->orchestration;
        $variables = ['definition' => $context->definition, 'orchestration' => $orchestration->toArray(), 'collections' => [], 'sequences' => [], 'resources' => []];
        foreach ($recipe['collections'] ?? [] as $path => $spec) {
            $variables['collections'][$path] = ['keys' => array_column(data_get($context->definition, $path, []), 'node_key')];
        }
        foreach ($recipe['sequences'] ?? [] as $key => $spec) {
            $items = [...($spec['prepend'] ?? []), ...data_get($context->definition, $spec['collection'], [])];
            $variables['sequences'][$key] = ['items' => $items, 'keys' => array_column($items, 'node_key')];
        }
        foreach ($recipe['resources'] ?? [] as $key => $spec) {
            $model = $spec['model']::firstOrCreate($spec['match'], $spec['attributes'] ?? []);
            $variables['resources'][$key] = $model->toArray();
        }

        $previousKeys = [];
        if ($context->previousDefinition) {
            $previousVariables = [...$variables, 'definition' => $context->previousDefinition];
            foreach ($recipe['nodes'] ?? [] as $spec) {
                if (isset($spec['reference'])) {
                    continue;
                }
                foreach ($this->values->contexts($spec, $previousVariables) as $local) {
                    $previousKeys[] = $spec['role'].':'.$this->values->resolve($spec['key'], $local);
                }
            }
        }
        $desired = [];
        foreach ($recipe['nodes'] ?? [] as $specKey => $spec) {
            foreach ($this->values->contexts($spec, $variables) as $local) {
                $role = $spec['role'];
                $key = $this->values->resolve($spec['key'], $local);
                $definition = $orchestration->schemaDefinition()->node($role);
                if (! $definition) {
                    throw ValidationException::withMessages(['role' => "Unknown recipe role [{$role}]."]);
                }
                $existing = $orchestration->nodes()->where('role', $role)->where('key', $key)->first();
                $ownership = isset($spec['reference']) ? 'linked' : 'owned';
                if ($existing && ($existing->ownership !== $ownership || (isset($existing->config['automation']) && $existing->config['automation'] !== $automation))) {
                    throw ValidationException::withMessages(['nodes' => "The node [{$role}:{$key}] has incompatible ownership."]);
                }
                if (isset($spec['reference'])) {
                    $id = $this->values->resolve($spec['reference'], $local);
                    $query = $definition->model::query();
                    foreach ($spec['required_relations'] ?? [] as $relation => $constraints) {
                        $query->whereHas($relation, fn ($related) => $related->where($constraints));
                    }
                    $model = $query->find($id);
                    if (! $model || (array_key_exists('is_active', $model->getAttributes()) && ! $model->is_active)) {
                        throw ValidationException::withMessages([ltrim(str_replace('definition.', '', $spec['reference']), '@') => 'Choisissez un élément actif.']);
                    }
                } else {
                    $model = $existing?->orchestratable ?? new $definition->model;
                    if (! $model->exists && isset($spec['identity_attribute'])) {
                        $column = $spec['identity_attribute'];
                        $base = $orchestration->key.'-'.$key;
                        $candidate = $base;
                        for ($suffix = 2; $definition->model::query()->where($column, $candidate)->exists(); $suffix++) {
                            $candidate = $base.'-'.$suffix;
                        }
                        $model->setAttribute($column, $candidate);
                    }
                    $attributes = $this->values->resolve($spec['attributes'] ?? [], $local);
                    foreach ($spec['merge_attributes'] ?? [] as $attribute) {
                        $attributes[$attribute] = array_replace_recursive($model->getAttribute($attribute) ?? [], $attributes[$attribute] ?? []);
                    }
                    $model->fill($attributes)->save();
                    foreach ($spec['media'] ?? [] as $mediaSpec) {
                        $this->media->synchronize($model, $mediaSpec, $this->values->resolve($mediaSpec['paths'], $local) ?? [], $automation);
                    }
                }
                $config = [...($existing?->config ?? []), ...$this->values->resolve($spec['config'] ?? [], $local),
                    'automation' => $automation, 'recipe_node' => $specKey,
                    'automation_id' => $local['item']['automation_id'] ?? $key];
                $node = $this->nodes->updateOrAttach($orchestration, $role, $key, $model, $ownership, $config, ($spec['order'] ?? 0) + ($local['position'] ?? 0));
                $desired[] = $node->id;
            }
        }
        // Only remove nodes managed by this recipe; manually configured nodes are preserved.
        foreach ($orchestration->nodes()->whereNotIn('id', $desired)->get() as $node) {
            $owner = $node->config['automation'] ?? null;
            $legacyManaged = ! $owner && $node->isOwned() && in_array($node->role.':'.$node->key, $previousKeys, true);
            if ($owner !== $automation && ! $legacyManaged) {
                continue;
            }
            $model = $node->orchestratable;
            $owned = $node->isOwned();
            $node->delete();
            if ($owned && $model) {
                $model->delete();
            }
        }
        $orchestration->update(['config' => [...$orchestration->config, ...$this->values->resolve($recipe['config'] ?? [], $variables)]]);
        $this->synchronizer->replace($orchestration, $this->graphs->build($recipe['triggers'] ?? [], $variables));
    }
}
