<?php

namespace CharlesStOlive\FilamentOrchestrator\Automations\Recipes;

use Illuminate\Database\Eloquent\Model;

final class RecipeMedia
{
    public function synchronize(Model $model, array $spec, array $paths, string $automation): void
    {
        $collection = $spec['collection'];
        $paths = array_values(array_unique(array_filter($paths, 'is_string')));
        $managed = $model->getMedia($collection)->filter(fn ($media) => $media->getCustomProperty('automation') === $automation);
        $managed->reject(fn ($media) => in_array($media->getCustomProperty('source_path'), $paths, true))->each->delete();
        $existing = $model->getMedia($collection)->map(fn ($media) => $media->getCustomProperty('source_path') ?: $media->getPathRelativeToRoot())->filter()->all();
        foreach (array_diff($paths, $existing) as $path) {
            $model->addMediaFromDisk($path, $spec['disk'] ?? 'public')
                ->withCustomProperties(['automation' => $automation, 'source_path' => $path])
                ->toMediaCollection($collection);
        }
    }
}
