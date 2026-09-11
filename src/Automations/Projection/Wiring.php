<?php

namespace CharlesStOlive\FilamentOrchestrator\Automations\Projection;

use CharlesStOlive\FilamentOrchestrator\Automations\Actions\ActionBlueprint;
use CharlesStOlive\FilamentOrchestrator\Automations\Actions\TriggerBlueprint;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Câblage « quand cet événement survient, exécute ces actions ».
 *
 * Chaque action est déclarée entièrement en arguments nommés : rien n'est
 * appliqué implicitement à « la dernière action ajoutée ».
 */
class Wiring
{
    protected ?string $sourceRole = null;

    protected ?string $sourceKey = null;

    protected array $conditions = [];

    /** @var array<int, ActionBlueprint> */
    protected array $actions = [];

    protected int $order = 0;

    protected ?string $key = null;

    protected ?string $label = null;

    protected bool $skipped = false;

    public function __construct(
        protected readonly Projection $projection,
        public readonly string $event,
        protected readonly ?Item $item = null,
    ) {
        if ($projection->schema()->event($event) === null) {
            throw new InvalidArgumentException(
                "L'événement [{$event}] n'est pas déclaré par le schéma [{$projection->schema()->key()}].",
            );
        }
    }

    /** Item ayant déclaré ce câblage, quand il en vient d'un. */
    public function item(): ?Item
    {
        return $this->item;
    }

    public function projection(): Projection
    {
        return $this->projection;
    }

    /**
     * Source de l'événement. Si le nœud visé n'a pas été matérialisé, le
     * câblage entier s'efface : une étape incomplète ne produit simplement pas
     * encore son déclencheur.
     */
    public function from(string $role, ?string $key = null): static
    {
        $this->sourceRole = $role;
        $this->sourceKey = $key;

        if ($key !== null && ! $this->projection->hasNode($role, $key)) {
            $this->skipped = true;
        }

        return $this;
    }

    public function isSkipped(): bool
    {
        return $this->skipped;
    }

    /** @param array<string, mixed> $conditions */
    public function when(array $conditions): static
    {
        $this->conditions = [...$this->conditions, ...$conditions];

        return $this;
    }

    /**
     * @param  array<string, mixed>  $with  Paramètres de l'action
     */
    public function run(
        string $action,
        ?string $role = null,
        ?string $key = null,
        array $with = [],
        ?string $label = null,
        ?int $order = null,
    ): static {
        if ($this->projection->schema()->action($action) === null) {
            throw new InvalidArgumentException(
                "L'action [{$action}] n'est pas déclarée par le schéma [{$this->projection->schema()->key()}].",
            );
        }

        // Une action visant un nœud non matérialisé est simplement omise.
        if ($role !== null && $key !== null && ! $this->projection->hasNode($role, $key)) {
            return $this;
        }

        $blueprint = ActionBlueprint::make(
            $this->uniqueActionKey($action, $key),
            $action,
            $label ?? $this->projection->schema()->action($action)?->label,
        )->parameters($with)->order($order ?? (count($this->actions) + 1) * 10);

        if ($role !== null && $key !== null) {
            $blueprint->target($role, $key);
        }

        $this->actions[] = $blueprint;

        return $this;
    }

    public function key(string $key): static
    {
        $this->key = $key;

        return $this;
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function order(int $order): static
    {
        $this->order = $order;

        return $this;
    }

    public function hasActions(): bool
    {
        return $this->actions !== [];
    }

    public function resolvedKey(): string
    {
        return $this->key ?? $this->defaultKey();
    }

    public function toBlueprint(): TriggerBlueprint
    {
        $trigger = TriggerBlueprint::make($this->resolvedKey(), $this->event, $this->label ?? $this->defaultLabel())
            ->order($this->order)
            ->actions(...$this->actions);

        if ($this->sourceRole !== null && $this->sourceKey !== null) {
            $trigger->source($this->sourceRole, $this->sourceKey);
        }

        if ($this->conditions !== []) {
            $trigger->when($this->conditions);
        }

        return $trigger;
    }

    protected function defaultKey(): string
    {
        return Str::slug(str_replace('.', '-', $this->event).($this->sourceKey ? '-'.$this->sourceKey : ''));
    }

    protected function defaultLabel(): string
    {
        return $this->projection->schema()->event($this->event)?->label ?? $this->event;
    }

    protected function uniqueActionKey(string $action, ?string $targetKey): string
    {
        $base = Str::slug(str_replace('.', '-', $action).($targetKey ? '-'.$targetKey : ''));
        $candidate = $base;
        $suffix = 2;

        while (collect($this->actions)->contains(fn (ActionBlueprint $blueprint): bool => $blueprint->key === $candidate)) {
            $candidate = $base.'-'.$suffix++;
        }

        return $candidate;
    }
}
