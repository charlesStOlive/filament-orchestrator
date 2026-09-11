<?php

namespace CharlesStOlive\FilamentOrchestrator\Automations\Projection;

use CharlesStOlive\FilamentOrchestrator\Automations\Actions\GraphBlueprint;
use CharlesStOlive\FilamentOrchestrator\Automations\Actions\TriggerBlueprint;
use CharlesStOlive\FilamentOrchestrator\Schemas\OrchestratorSchema;
use Closure;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Décrit ce qu'un formulaire d'automatisation fabrique dans une orchestration :
 * des nœuds et le câblage événementiel entre eux.
 *
 * La projection est évaluée avec les données du formulaire en main : les
 * valeurs sont donc résolues immédiatement, sans gabarit ni interpolation.
 * Un schéma peut fournir sa propre sous-classe pour exposer son vocabulaire
 * (voir projectionClass() / itemClass() sur OrchestratorSchema).
 */
class Projection
{
    /** @var array<int, NodeDeclaration> */
    protected array $nodes = [];

    /** @var array<int, Wiring> */
    protected array $wirings = [];

    /** @var array<int, TriggerBlueprint> */
    protected array $triggers = [];

    protected array $config = [];

    /** Données normalisées au fil des déclarations (identifiants et clés stables). */
    protected array $definition;

    /** @var array<int, string> */
    protected array $usedKeys = [];

    public function __construct(
        protected readonly OrchestratorSchema $schema,
        protected readonly array $data,
    ) {
        $this->definition = $data;
    }

    public static function make(OrchestratorSchema $schema, array $data): static
    {
        return new static($schema, $data);
    }

    /** Classe d'item utilisée par each() ; un schéma la remplace par la sienne. */
    public static function itemClass(): string
    {
        return Item::class;
    }

    public function schema(): OrchestratorSchema
    {
        return $this->schema;
    }

    public function value(string $path, mixed $default = null): mixed
    {
        return data_get($this->data, $path, $default);
    }

    /** Déclare un nœud unique, dont la clé est fixe. */
    public function node(string $role, string $key): NodeDeclaration
    {
        $definition = $this->schema->node($role);

        if ($definition === null) {
            throw new InvalidArgumentException(
                "Le rôle [{$role}] n'est pas déclaré par le schéma [{$this->schema->key()}].",
            );
        }

        $this->usedKeys[] = $key;

        $declaration = new NodeDeclaration($role, $key, $definition);
        $this->nodes[] = $declaration;

        return $declaration;
    }

    /**
     * Déclare une collection d'éléments issue d'un chemin du formulaire
     * (typiquement un Repeater). Chaque ligne reçoit un identifiant stable et
     * une clé de nœud lisible, puis passe dans le callback.
     *
     * @param  Closure(Item): mixed  $callback
     */
    public function each(string $path, Closure $callback, ?string $keyFrom = null, string $keyFallback = 'item'): static
    {
        $rows = $this->value($path, []);
        $rows = is_array($rows) ? array_values($rows) : [];
        $normalized = [];
        $itemClass = static::itemClass();

        foreach ($rows as $index => $row) {
            $row = is_array($row) ? $row : [];
            $row['automation_id'] = filled($row['automation_id'] ?? null)
                ? (string) $row['automation_id']
                : (string) Str::uuid();
            $row['node_key'] = $this->claimKey(
                filled($row['node_key'] ?? null)
                    ? (string) $row['node_key']
                    : (Str::slug((string) ($keyFrom ? ($row[$keyFrom] ?? '') : '')) ?: $keyFallback),
            );

            $item = new $itemClass($this, $path, $index, $row);
            $callback($item);

            $normalized[] = $row;
        }

        data_set($this->definition, $path, $normalized);

        return $this;
    }

    /** Point d'entrée du câblage : « quand cet événement survient… ». */
    public function on(string $event): Wiring
    {
        $wiring = new Wiring($this, $event);
        $this->wirings[] = $wiring;

        return $wiring;
    }

    /** Échappatoire : un déclencheur écrit à la main, pour les cas hors sucre. */
    public function trigger(TriggerBlueprint $trigger): static
    {
        $this->triggers[] = $trigger;

        return $this;
    }

    /** @param array<string, mixed|Closure> $config */
    public function config(array $config): static
    {
        $this->config = [...$this->config, ...$config];

        return $this;
    }

    /**
     * Nœuds déclarés pour un rôle, dans l'ordre de déclaration. Permet aux
     * sucres transverses (enchaînement de contenus, par exemple) de relire ce
     * qui a déjà été déclaré.
     *
     * @return array<int, NodeDeclaration>
     */
    public function declaredNodes(?string $role = null, bool $materializedOnly = true): array
    {
        return array_values(array_filter(
            $this->nodes,
            fn (NodeDeclaration $node): bool => ($role === null || $node->role === $role)
                && (! $materializedOnly || $node->isMaterialized()),
        ));
    }

    public function compile(): CompiledProjection
    {
        $graph = GraphBlueprint::make();
        $usedTriggerKeys = [];

        foreach ($this->wirings as $wiring) {
            if (! $wiring->hasActions()) {
                continue;
            }

            $key = $wiring->resolvedKey();
            $candidate = $key;
            $suffix = 2;

            while (in_array($candidate, $usedTriggerKeys, true)) {
                $candidate = $key.'-'.$suffix++;
            }

            $usedTriggerKeys[] = $candidate;
            $graph->triggers($wiring->key($candidate)->toBlueprint());
        }

        foreach ($this->triggers as $trigger) {
            $graph->triggers($trigger);
        }

        return new CompiledProjection($this->definition, $this->nodes, $graph, $this->config);
    }

    protected function claimKey(string $base): string
    {
        $candidate = $base;
        $suffix = 2;

        while (in_array($candidate, $this->usedKeys, true)) {
            $candidate = $base.'-'.$suffix++;
        }

        $this->usedKeys[] = $candidate;

        return $candidate;
    }
}
