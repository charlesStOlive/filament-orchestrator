<?php

namespace CharlesStOlive\FilamentOrchestrator\Library;

use CharlesStOlive\FilamentOrchestrator\Models\LibraryMedia;
use Illuminate\Support\Collection;

/**
 * Une action que l'application ajoute à la bibliothèque : elle rejoint le menu
 * de la sélection, à côté de « Ajouter des tags » ou « Supprimer », et peut
 * marquer d'une icône les images qu'elle concerne.
 *
 * On la crée en étendant cette classe et en la déclarant dans
 * `filament-orchestrator.library.actions` :
 *
 *     class HeaderImageAction extends LibraryAction
 *     {
 *         protected function setUp(): void
 *         {
 *             $this->name('header')->label('Image d’en-tête')->setIcon('heroicon-s-star')->single();
 *         }
 *
 *         public function appliesTo(LibraryContext $context): bool { return $context->hasFocus(); }
 *         public function marks(LibraryMedia $media, LibraryContext $context): bool { … }
 *         public function handle(Collection $media, LibraryContext $context): ?string { … }
 *     }
 *
 * `appliesTo()` décide si l'action est proposée : la bibliothèque générale et
 * celle ouverte depuis une journée n'ont pas les mêmes.
 */
abstract class LibraryAction
{
    protected string $name = '';

    protected string $label = '';

    protected string $icon = 'heroicon-o-sparkles';

    protected bool $single = false;

    final public function __construct()
    {
        $this->setUp();
    }

    public static function make(): static
    {
        return new static;
    }

    protected function setUp(): void {}

    /** Identifiant de l'action : lettres, chiffres et tirets. */
    public function name(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /** L'icône de l'action, dans le menu, et celle qui marque les images qu'elle concerne. */
    public function setIcon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /** L'action porte sur une seule image : la bibliothèque refuse une sélection plus large. */
    public function single(bool $single = true): static
    {
        $this->single = $single;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function isSingle(): bool
    {
        return $this->single;
    }

    /** Cette action est-elle proposée dans cette bibliothèque ? */
    public function appliesTo(LibraryContext $context): bool
    {
        return true;
    }

    /** Cette image porte-t-elle la marque de l'action (son icône s'affiche sur sa carte) ? */
    public function marks(LibraryMedia $media, LibraryContext $context): bool
    {
        return false;
    }

    /**
     * Exécute l'action sur les images cochées.
     *
     * @param  Collection<int, LibraryMedia>  $media
     * @return string|null Le message de la notification de succès.
     */
    abstract public function handle(Collection $media, LibraryContext $context): ?string;
}
