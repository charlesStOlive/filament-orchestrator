<?php

namespace CharlesStOlive\FilamentOrchestrator\Automations\Projection;

/**
 * Un élément d'une collection du formulaire (une ligne de Repeater).
 *
 * L'item ne connaît aucun vocabulaire métier : il expose les primitives
 * (node(), on(), value()). Les méthodes parlantes — point(), content(),
 * whenPointClicked() — sont apportées par la sous-classe du schéma, qui est
 * aussi l'endroit où les événements et actions correspondants sont déclarés.
 */
class Item
{
    public function __construct(
        protected readonly Projection $projection,
        public readonly string $path,
        public readonly int $index,
        protected readonly array $row,
    ) {}

    /** Clé de nœud stable et lisible, partagée par les nœuds de cet item. */
    public function key(): string
    {
        return (string) $this->row['node_key'];
    }

    /** Identifiant stable de la ligne, indépendant de son libellé et de sa position. */
    public function automationId(): string
    {
        return (string) $this->row['automation_id'];
    }

    public function position(): int
    {
        return $this->index + 1;
    }

    public function value(string $field, mixed $default = null): mixed
    {
        return data_get($this->row, $field, $default);
    }

    public function values(): array
    {
        return $this->row;
    }

    public function projection(): Projection
    {
        return $this->projection;
    }

    /** Déclare un nœud porté par cet item ; la clé vaut celle de l'item par défaut. */
    public function node(string $role, ?string $key = null): NodeDeclaration
    {
        return $this->projection
            ->node($role, $key ?? $this->key())
            ->automationId($this->automationId());
    }

    public function on(string $event): Wiring
    {
        return $this->projection->on($event);
    }
}
