<?php

namespace CharlesStOlive\FilamentOrchestrator\Automations;

use CharlesStOlive\FilamentOrchestrator\Automations\Projection\NodeDeclaration;
use CharlesStOlive\FilamentOrchestrator\Events\AutomationNodeRemoved;
use CharlesStOlive\FilamentOrchestrator\Models\Orchestration;
use CharlesStOlive\FilamentOrchestrator\Models\OrchestratorNode;
use CharlesStOlive\FilamentOrchestrator\Services\NodeManager;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

/**
 * Applique les nœuds déclarés par une projection à une orchestration.
 *
 * Ne touche jamais aux nœuds ajoutés à la main : seuls ceux portant la clé de
 * l'automatisation dans leur configuration sont créés, mis à jour ou supprimés.
 */
final class NodeSynchronizer
{
    public function __construct(private readonly NodeManager $nodes) {}

    /** @param array<int, NodeDeclaration> $declarations */
    public function synchronize(Orchestration $orchestration, array $declarations, string $automation): void
    {
        $keptIds = [];

        foreach ($declarations as $declaration) {
            if (! $declaration->isMaterialized()) {
                continue;
            }

            $keptIds[] = $this->apply($orchestration, $declaration, $automation)->getKey();
        }

        $this->prune($orchestration, $keptIds, $automation);
    }

    private function apply(Orchestration $orchestration, NodeDeclaration $declaration, string $automation): OrchestratorNode
    {
        $existing = $this->existingNode($orchestration, $declaration);

        $model = $declaration->isLinked()
            ? $this->resolveReference($declaration)
            : $this->persistModel($orchestration, $declaration, $existing, $automation);

        return $this->nodes->updateOrAttach(
            $orchestration,
            $declaration->role,
            $declaration->key,
            $model,
            $declaration->resolvedOwnership(),
            [
                ...($existing?->config ?? []),
                ...$this->resolve($declaration->getConfig(), $orchestration),
                'automation' => $automation,
                'automation_id' => $declaration->getAutomationId() ?? $declaration->key,
            ],
            $declaration->getOrder(),
        );
    }

    private function persistModel(
        Orchestration $orchestration,
        NodeDeclaration $declaration,
        ?OrchestratorNode $existing,
        string $automation,
    ): Model {
        $class = $declaration->model();
        $model = $existing?->orchestratable;

        if (! $model instanceof $class) {
            $model = new $class;
        }

        if (! $model->exists && $column = $declaration->getIdentityColumn()) {
            $model->setAttribute($column, $this->uniqueValue(
                $class,
                $column,
                $declaration->getIdentityBase() ?: $orchestration->key.'-'.$declaration->key,
            ));
        }

        $attributes = $this->resolve($declaration->getAttributes(), $orchestration);

        foreach ($this->resolve($declaration->getMergedAttributes(), $orchestration) as $key => $value) {
            $attributes[$key] = array_replace_recursive(
                (array) ($model->getAttribute($key) ?? []),
                (array) $value,
            );
        }

        $model->fill($attributes)->save();

        foreach ($declaration->getMedia() as $collection => $spec) {
            $this->synchronizeMedia($model, $collection, $spec['paths'], $spec['disk'], $automation);
        }

        return $model;
    }

    private function resolveReference(NodeDeclaration $declaration): Model
    {
        $class = $declaration->model();
        $query = $class::query()->where($declaration->getReferenceWhere());

        foreach ($declaration->getReferenceWhereHas() as $relation => $constraints) {
            $query->whereHas($relation, fn ($related) => $related->where($constraints));
        }

        $model = $query->find($declaration->getReference());

        if (! $model instanceof Model) {
            throw ValidationException::withMessages([
                $declaration->getReferenceErrorField() ?? $declaration->role => 'Choisissez un élément actif et disponible.',
            ]);
        }

        return $model;
    }

    /**
     * Les médias posés par l'automatisation sont tracés par leur chemin source,
     * ce qui permet de les remplacer sans toucher à ceux ajoutés manuellement.
     *
     * @param  array<int, string>  $paths
     */
    private function synchronizeMedia(Model $model, string $collection, array $paths, string $disk, string $automation): void
    {
        if (! method_exists($model, 'getMedia')) {
            return;
        }

        $managed = $model->getMedia($collection)
            ->filter(fn ($media): bool => $media->getCustomProperty('automation') === $automation);

        $managed
            ->reject(fn ($media): bool => in_array($media->getCustomProperty('source_path'), $paths, true))
            ->each->delete();

        $existing = $managed
            ->map(fn ($media): mixed => $media->getCustomProperty('source_path'))
            ->filter()
            ->all();

        foreach (array_diff($paths, $existing) as $path) {
            $model->addMediaFromDisk($path, $disk)
                ->withCustomProperties(['automation' => $automation, 'source_path' => $path])
                ->toMediaCollection($collection);
        }
    }

    /** @param array<int, int|string> $keptIds */
    private function prune(Orchestration $orchestration, array $keptIds, string $automation): void
    {
        $obsolete = $orchestration->nodes()
            ->when($keptIds !== [], fn ($query) => $query->whereNotIn('id', $keptIds))
            ->get()
            ->filter(fn (OrchestratorNode $node): bool => ($node->config['automation'] ?? null) === $automation);

        foreach ($obsolete as $node) {
            $model = $node->orchestratable;
            $owned = $node->isOwned();

            Event::dispatch(new AutomationNodeRemoved($node, $model instanceof Model ? $model : null, $automation));

            $node->delete();

            if ($owned && $model instanceof Model) {
                $model->delete();
            }
        }
    }

    /** @param array<string, mixed|Closure> $values */
    private function resolve(array $values, Orchestration $orchestration): array
    {
        return array_map(
            fn (mixed $value): mixed => $value instanceof Closure ? $value($orchestration) : $value,
            $values,
        );
    }

    private function uniqueValue(string $class, string $column, string $base): string
    {
        $base = $base ?: 'node';
        $candidate = $base;
        $suffix = 2;

        while ($class::query()->where($column, $candidate)->exists()) {
            $candidate = $base.'-'.$suffix++;
        }

        return $candidate;
    }
}
