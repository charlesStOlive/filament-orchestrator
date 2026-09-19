<?php

namespace CharlesStOlive\FilamentOrchestrator\Automations\Projection;

use CharlesStOlive\FilamentOrchestrator\Library\LibraryImages;
use CharlesStOlive\FilamentOrchestrator\Models\OrchestratorNode;
use CharlesStOlive\FilamentOrchestrator\Schemas\Definitions\NodeDefinition;
use Closure;
use InvalidArgumentException;

/**
 * Déclaration d'un nœud à projeter dans une orchestration.
 *
 * Un nœud « owned » crée et met à jour son modèle ; un nœud « linked » se
 * contente de référencer un modèle existant. Les valeurs sont déjà résolues
 * par l'appelant : une valeur peut être un scalaire ou une Closure recevant
 * l'orchestration, jamais un gabarit à interpréter.
 */
class NodeDeclaration
{
    protected array $attributes = [];

    protected array $mergedAttributes = [];

    protected array $media = [];

    protected array $config = [];

    protected int $order = 0;

    protected bool $materialized = true;

    protected ?string $identityColumn = null;

    protected ?string $identityBase = null;

    protected mixed $reference = null;

    protected array $referenceWhere = [];

    protected array $referenceWhereHas = [];

    protected ?string $referenceErrorField = null;

    protected ?string $ownership = null;

    protected ?string $automationId = null;

    public function __construct(
        public readonly string $role,
        public readonly string $key,
        public readonly NodeDefinition $definition,
    ) {}

    /**
     * Le nœud n'existe que si toutes les valeurs fournies sont renseignées.
     * C'est ce qui permet la matérialisation progressive : pas de coordonnées,
     * pas de hotpoint ; pas de texte, pas de contenu.
     */
    public function when(mixed ...$values): static
    {
        foreach ($values as $value) {
            if (blank($value)) {
                $this->materialized = false;
            }
        }

        return $this;
    }

    public function unless(mixed ...$values): static
    {
        foreach ($values as $value) {
            if (filled($value)) {
                $this->materialized = false;
            }
        }

        return $this;
    }

    /** @param array<string, mixed|Closure> $attributes */
    public function attributes(array $attributes): static
    {
        $this->attributes = [...$this->attributes, ...$attributes];

        return $this;
    }

    /**
     * Attributs fusionnés avec la valeur déjà présente sur le modèle plutôt
     * que remplacés — utile pour les colonnes JSON éditées à la main.
     *
     * @param  array<string, mixed|Closure>  $attributes
     */
    public function merge(array $attributes): static
    {
        $this->mergedAttributes = [...$this->mergedAttributes, ...$attributes];

        return $this;
    }

    /**
     * Colonne servant d'identifiant lisible (slug, clé) générée une seule fois
     * à la création du modèle, et rendue unique par le synchroniseur.
     */
    public function identity(string $column, ?string $base = null): static
    {
        $this->identityColumn = $column;
        $this->identityBase = $base;

        return $this;
    }

    /** @param array<int, string> $paths */
    public function media(string $collection, array $paths, string $disk = 'public'): static
    {
        $this->media[$collection] = [
            'paths' => array_values(array_filter($paths, 'is_string')),
            'disk' => $disk,
        ];

        return $this;
    }

    /**
     * Ce nœud affiche les images de la bibliothèque qui portent ces tags.
     *
     * Rien n'est copié : les images restent dans la bibliothèque de
     * l'orchestration, et le nœud ne garde que la liste des tags à lire. Une
     * liste vide retire les tags précédemment déclarés.
     *
     * @param  array<int, string>  $tags
     */
    public function library(array $tags): static
    {
        return $this->config([LibraryImages::NODE_CONFIG_KEY => array_values(array_unique($tags))]);
    }

    /** @param array<string, mixed|Closure> $config */
    public function config(array $config): static
    {
        $this->config = [...$this->config, ...$config];

        return $this;
    }

    public function order(int $order): static
    {
        $this->order = $order;

        return $this;
    }

    /**
     * Référence un modèle existant au lieu d'en créer un.
     *
     * @param  array<string, mixed>  $where  Contraintes sur le modèle lui-même
     * @param  array<string, array<string, mixed>>  $whereHas  Contraintes sur ses relations
     */
    public function reference(mixed $id, array $where = [], array $whereHas = [], ?string $errorField = null): static
    {
        $this->reference = $id;
        $this->referenceWhere = $where;
        $this->referenceWhereHas = $whereHas;
        $this->referenceErrorField = $errorField;
        $this->ownership = OrchestratorNode::OwnershipLinked;

        return $this->when($id);
    }

    public function ownership(string $ownership): static
    {
        if (! in_array($ownership, $this->definition->ownerships, true)) {
            throw new InvalidArgumentException(
                "L'appartenance [{$ownership}] n'est pas autorisée pour le rôle [{$this->role}].",
            );
        }

        $this->ownership = $ownership;

        return $this;
    }

    public function automationId(?string $id): static
    {
        $this->automationId = $id;

        return $this;
    }

    public function isMaterialized(): bool
    {
        return $this->materialized;
    }

    public function isLinked(): bool
    {
        return $this->reference !== null;
    }

    public function model(): string
    {
        return $this->definition->model;
    }

    public function resolvedOwnership(): string
    {
        return $this->ownership
            ?? ($this->isLinked() ? OrchestratorNode::OwnershipLinked : $this->definition->defaultOwnership);
    }

    public function getReference(): mixed
    {
        return $this->reference;
    }

    public function getReferenceWhere(): array
    {
        return $this->referenceWhere;
    }

    public function getReferenceWhereHas(): array
    {
        return $this->referenceWhereHas;
    }

    public function getReferenceErrorField(): ?string
    {
        return $this->referenceErrorField;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getMergedAttributes(): array
    {
        return $this->mergedAttributes;
    }

    public function getMedia(): array
    {
        return $this->media;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function getOrder(): int
    {
        return $this->order;
    }

    public function getIdentityColumn(): ?string
    {
        return $this->identityColumn;
    }

    public function getIdentityBase(): ?string
    {
        return $this->identityBase;
    }

    public function getAutomationId(): ?string
    {
        return $this->automationId;
    }
}
