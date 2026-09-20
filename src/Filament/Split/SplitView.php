<?php

namespace CharlesStOlive\FilamentOrchestrator\Filament\Split;

use Closure;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;

/**
 * Le contenu d'une page en deux blocs côte à côte : le contenu principal (le
 * formulaire) à gauche, un volet latéral à droite, séparés par une barre que
 * l'on glisse pour ajuster la largeur de chacun.
 *
 * Contrairement à un slide-over, les deux blocs restent utilisables ensemble —
 * c'est ce qui permettra d'y faire des glisser-déposer de l'un vers l'autre.
 *
 * C'est un composant de schéma ordinaire : la page garde son en-tête, ses
 * actions et ses modales, seul son contenu est enveloppé. Les pages n'ont en
 * général pas à l'utiliser directement, le trait HasSidePane s'en charge.
 *
 *     SplitView::make()
 *         ->main($formSchema)
 *         ->pane(fn () => [...])     // null ou vide : le volet est fermé
 *         ->paneHeading('Bibliothèque', 'heroicon-o-photo')
 *
 * Le volet se ferme par `wire:click="closeSidePane"` : la page qui l'utilise
 * doit donc porter cette méthode (HasSidePane la fournit).
 *
 * La largeur du volet est un pourcentage de la largeur totale. Elle se retient
 * dans le navigateur, par `storageKey`, pour qu'un réglage manuel survive
 * d'une page à l'autre ; la barre, double-cliquée, la remet à la valeur par
 * défaut. En dessous de `lg`, il n'y a pas assez de place pour deux colonnes :
 * le volet passe sous le contenu, sans barre.
 */
class SplitView extends Component
{
    protected string $view = 'filament-orchestrator::split.split-view';

    protected int|Closure $paneWidth = 33;

    protected int|Closure $minPaneWidth = 20;

    protected int|Closure $maxPaneWidth = 60;

    protected string|Closure|null $storageKey = null;

    protected string|Closure|null $paneKey = null;

    protected string|Closure|null $paneHeading = null;

    protected string|Closure|null $paneIcon = null;

    final public function __construct() {}

    public static function make(): static
    {
        $static = app(static::class);
        $static->configure();

        return $static;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->columnSpanFull();
    }

    /** @param array<int, Component>|Schema|Closure $components */
    public function main(array|Schema|Closure $components): static
    {
        $this->childComponents($components, 'main');

        return $this;
    }

    /**
     * Le contenu du volet. Vide ou `null`, le volet est fermé : rien n'est
     * dessiné, et la place revient tout entière au contenu principal.
     *
     * @param  array<int, Component>|Closure|null  $components
     */
    public function pane(array|Closure|null $components): static
    {
        $this->childComponents($components, 'pane');

        return $this;
    }

    public function paneHeading(string|Closure|null $label, string|Closure|null $icon = null): static
    {
        $this->paneHeading = $label;
        $this->paneIcon = $icon;

        return $this;
    }

    /**
     * Ce qui identifie le contenu du volet : quand il change (un autre volet
     * s'ouvre), le volet est redessiné à neuf plutôt que recyclé.
     */
    public function paneKey(string|Closure|null $key): static
    {
        $this->paneKey = $key;

        return $this;
    }

    /**
     * La largeur du volet, en pourcentage de la largeur totale : sa valeur par
     * défaut (un tiers) et les bornes entre lesquelles on peut la glisser.
     */
    public function paneWidth(int|Closure $default, int|Closure $min = 20, int|Closure $max = 60): static
    {
        $this->paneWidth = $default;
        $this->minPaneWidth = $min;
        $this->maxPaneWidth = $max;

        return $this;
    }

    /** Sous quel nom le navigateur retient la largeur choisie à la main. */
    public function storageKey(string|Closure|null $key): static
    {
        $this->storageKey = $key;

        return $this;
    }

    public function getPaneWidth(): int
    {
        return (int) $this->evaluate($this->paneWidth);
    }

    public function getMinPaneWidth(): int
    {
        return (int) $this->evaluate($this->minPaneWidth);
    }

    public function getMaxPaneWidth(): int
    {
        return (int) $this->evaluate($this->maxPaneWidth);
    }

    public function getStorageKey(): string
    {
        return (string) ($this->evaluate($this->storageKey) ?? 'default');
    }

    public function getPaneKey(): string
    {
        return (string) ($this->evaluate($this->paneKey) ?? 'pane');
    }

    public function getPaneHeading(): ?string
    {
        return $this->evaluate($this->paneHeading);
    }

    public function getPaneIcon(): ?string
    {
        return $this->evaluate($this->paneIcon);
    }
}
