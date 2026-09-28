<?php

namespace CharlesStOlive\FilamentOrchestrator\Library;

use CharlesStOlive\FilamentOrchestrator\Models\LibraryMedia;
use Filament\Schemas\Components\Component;
use Filament\Support\Enums\Width;
use Illuminate\Support\Collection;
use LogicException;

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
 *
 * **Une action qui demande des réglages** déclare un formulaire (`schema()`) :
 * la bibliothèque l'ouvre dans une modale, avec les images cochées sous la
 * main, et passe ce qui a été saisi à `submit()` plutôt qu'à `handle()`. Les
 * refus (`single()`, `accepts()`) sont dits avant que la modale s'ouvre.
 *
 *     class CaptionAction extends LibraryAction
 *     {
 *         public function schema(Collection $media, LibraryContext $context): ?array
 *         {
 *             return [TextInput::make('caption')->label('Légende')->required()];
 *         }
 *
 *         public function submit(Collection $media, LibraryContext $context, array $data): ?string { … }
 *     }
 */
abstract class LibraryAction
{
    protected string $name = '';

    protected string $label = '';

    protected string $icon = 'heroicon-o-sparkles';

    protected bool $single = false;

    protected bool $shortcut = false;

    protected ?string $shortLabel = null;

    protected string $refusal = 'Cette action ne s’applique pas à cette sélection';

    protected Width|string $modalWidth = Width::TwoExtraLarge;

    protected ?string $modalSubmitLabel = null;

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

    /**
     * L'action est un bouton toujours visible de la barre d'outils, à côté de ceux qui ne dépendent pas de la sélection,
     * plutôt qu'une entrée du menu « Sélection » : pour les gestes qu'on fait sans cesse.
     */
    public function shortcut(bool $shortcut = true): static
    {
        $this->shortcut = $shortcut;

        return $this;
    }

    public function isShortcut(): bool
    {
        return $this->shortcut;
    }

    /**
     * Le libellé du bouton quand l'action est un raccourci de la barre d'outils, où la place manque : le libellé complet
     * devient son infobulle.
     */
    public function shortLabel(string $label): static
    {
        $this->shortLabel = $label;

        return $this;
    }

    public function getShortLabel(): string
    {
        return $this->shortLabel ?? $this->label;
    }

    /** Ce que dit la bibliothèque quand une image cochée n'est pas de celles que l'action accepte (voir `accepts()`). */
    public function refusal(string $message): static
    {
        $this->refusal = $message;

        return $this;
    }

    public function getRefusal(): string
    {
        return $this->refusal;
    }

    /**
     * Cette image, ou cette vidéo, est-elle de celles auxquelles l'action s'applique ? Une seule refusée, et l'action
     * ne fait rien : la bibliothèque dit pourquoi (`refusal()`). Tout est accepté par défaut.
     */
    public function accepts(LibraryMedia $media): bool
    {
        return true;
    }

    /**
     * Ce que l'action trouve à redire à la sélection entière (trop peu d'images, trop...), dit avant
     * d'ouvrir sa modale ou de l'exécuter ; `null` : rien. S'ajoute à `single()` et `accepts()`.
     *
     * @param  Collection<int, LibraryMedia>  $media
     */
    public function refuses(Collection $media): ?string
    {
        return null;
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
     * Le formulaire de réglages de l'action, ouvert en modale avant de l'exécuter ; `null` : aucun,
     * l'action s'exécute au clic (`handle()`).
     *
     * @param  Collection<int, LibraryMedia>  $media  Les images cochées.
     * @return array<int, Component>|null
     */
    public function schema(Collection $media, LibraryContext $context): ?array
    {
        return null;
    }

    public function modalWidth(Width|string $width): static
    {
        $this->modalWidth = $width;

        return $this;
    }

    public function getModalWidth(): Width|string
    {
        return $this->modalWidth;
    }

    /** Le libellé du bouton qui valide la modale de réglages. */
    public function modalSubmitLabel(?string $label): static
    {
        $this->modalSubmitLabel = $label;

        return $this;
    }

    public function getModalSubmitLabel(): ?string
    {
        return $this->modalSubmitLabel;
    }

    /**
     * Exécute l'action sur les images cochées.
     *
     * @param  Collection<int, LibraryMedia>  $media
     * @return string|null Le message de la notification de succès.
     */
    public function handle(Collection $media, LibraryContext $context): ?string
    {
        throw new LogicException(static::class.' doit définir handle() — ou submit(), si elle a un formulaire (schema()).');
    }

    /**
     * Exécute l'action avec les réglages saisis dans son formulaire (`schema()`). Sans formulaire,
     * `$data` est vide et c'est `handle()` qui s'exécute.
     *
     * @param  Collection<int, LibraryMedia>  $media
     * @param  array<string, mixed>  $data
     * @return string|null Le message de la notification de succès.
     */
    public function submit(Collection $media, LibraryContext $context, array $data): ?string
    {
        return $this->handle($media, $context);
    }
}
