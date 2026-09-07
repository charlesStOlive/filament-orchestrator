<?php

namespace CharlesStOlive\FilamentOrchestrator\Automations\Recipes;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class RecipeNormalizer
{
    public function normalize(array $recipe, array $data, array $previous): array
    {
        $merged = [...$previous, ...$data];
        foreach ($recipe['merge_objects'] ?? [] as $path) {
            data_set($merged, $path, [...data_get($previous, $path, []), ...data_get($data, $path, [])]);
        }
        foreach ($recipe['defaults'] ?? [] as $path => $default) {
            if (! Arr::has($merged, $path)) {
                data_set($merged, $path, $default);
            }
        }
        $validated = Validator::make($merged, $recipe['rules'] ?? [])->validate();
        foreach ($recipe['collections'] ?? [] as $path => $collection) {
            $usedKeys = $collection['reserved_keys'] ?? [];
            $usedIds = [];
            $old = collect(data_get($previous, $path, []))->keyBy('automation_id');
            $items = [];
            foreach (array_values(data_get($merged, $path, [])) as $item) {
                $item = [...($collection['defaults'] ?? []), ...$item];
                $id = (string) ($item['automation_id'] ?? Str::uuid());
                if ($id === '' || in_array($id, $usedIds, true)) {
                    $id = (string) Str::uuid();
                    unset($item['node_key']);
                }
                $usedIds[] = $id;
                // Persisted identity wins over a renamed label or a submitted replacement key.
                $key = $old->get($id)['node_key'] ?? $item['node_key'] ?? Str::slug((string) data_get($item, $collection['key_from']));
                $base = $key ?: 'item';
                $key = $base;
                for ($suffix = 2; in_array($key, $usedKeys, true); $suffix++) {
                    $key = $base.'-'.$suffix;
                }
                $usedKeys[] = $key;
                $items[] = [...$item, 'automation_id' => $id, 'node_key' => $key];
            }
            data_set($validated, $path, $items);
        }

        return $validated;
    }
}
