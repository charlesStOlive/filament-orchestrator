<?php

namespace CharlesStOlive\FilamentOrchestrator\Automations\Recipes;

use Illuminate\Support\Arr;
use InvalidArgumentException;

final class RecipeValues
{
    public function resolve(mixed $value, array $context): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($item) => $this->resolve($item, $context), $value);
        }
        if (! is_string($value)) {
            return $value;
        }
        if (str_starts_with($value, '@')) {
            $path = substr($value, 1);
            if (! Arr::has($context, $path)) {
                throw new InvalidArgumentException("Unknown recipe reference [{$path}].");
            }

            return data_get($context, $path);
        }

        return preg_replace_callback('/\{\{([a-zA-Z0-9_.]+)\}\}/', function ($match) use ($context) {
            $resolved = $this->resolve('@'.$match[1], $context);
            if (! is_scalar($resolved) && $resolved !== null) {
                throw new InvalidArgumentException('A template requires a scalar value.');
            }

            return (string) $resolved;
        }, $value);
    }

    public function contexts(array $spec, array $context): array
    {
        if (! isset($spec['each'])) {
            return [$context];
        }
        $items = $this->resolve($spec['each'], $context);
        if (! is_array($items)) {
            throw new InvalidArgumentException('Recipe each must reference an array.');
        }
        $items = array_values($items);

        return array_map(fn ($item, $index) => [...$context, 'item' => $item, 'index' => $index, 'position' => $index + 1, 'next' => $items[$index + 1] ?? null], $items, array_keys($items));
    }
}
