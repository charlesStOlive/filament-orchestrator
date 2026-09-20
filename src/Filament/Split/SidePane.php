<?php

namespace CharlesStOlive\FilamentOrchestrator\Filament\Split;

use Closure;
use Filament\Actions\Action;
use Filament\Schemas\Components\Component;
use Illuminate\Support\Str;

/**
 * Un contenu que le volet latéral d'une page (voir HasSidePane) peut accueillir :
 * la bibliothèque d'images, plus tard un panneau d'événements, etc.
 *
 * C'est une simple définition — un nom, un libellé, une icône et les composants
 * de schéma à afficher. Elle ne dessine rien : la page la range dans
 * `getSidePanes()`, et le volet n'en rend que le contenu, seulement une fois
 * ouvert, si bien qu'un volet fermé ne coûte rien.
 *
 *     SidePane::make('library')
 *         ->label('Bibliothèque')
 *         ->icon('heroicon-o-photo')
 *         ->schema([Livewire::make(MediaLibraryTable::class, [...])])
 */
class SidePane
{
    protected string|Closure|null $label = null;

    protected string|Closure|null $icon = null;

    /** @var array<int, Component>|Closure */
    protected array|Closure $schema = [];

    final public function __construct(protected string $name) {}

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function label(string|Closure|null $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function icon(string|Closure|null $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Les composants du volet, ou une fonction qui les construit : elle reçoit le
     * contexte que la page a donné à ce volet (`fn (array $context): array`),
     * pour que le volet s'ouvre déjà réglé sur le travail en cours.
     *
     * @param  array<int, Component>|Closure(array<string, mixed>): array<int, Component>  $components
     */
    public function schema(array|Closure $components): static
    {
        $this->schema = $components;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return value($this->label) ?? Str::headline($this->name);
    }

    public function getIcon(): ?string
    {
        return value($this->icon);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, Component>
     */
    public function getSchema(array $context = []): array
    {
        return value($this->schema, $context);
    }

    /**
     * Le bouton, à poser où l'on veut (le plus souvent dans les actions
     * d'en-tête de la page) : il ouvre ce volet, ou le ferme s'il est déjà
     * ouvert. Il prend la couleur primaire tant que le volet est ouvert, pour
     * qu'on sache à quoi il correspond.
     */
    public function toggleAction(?string $name = null): Action
    {
        return Action::make($name ?? 'pane'.Str::studly($this->name))
            ->label(fn (): string => $this->getLabel())
            ->icon(fn (): ?string => $this->getIcon())
            ->color(fn ($livewire): string => method_exists($livewire, 'isSidePaneOpen') && $livewire->isSidePaneOpen($this->name)
                ? 'primary'
                : 'gray')
            ->action(fn ($livewire) => $livewire->toggleSidePane($this->name));
    }
}
