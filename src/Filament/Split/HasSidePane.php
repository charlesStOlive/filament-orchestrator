<?php

namespace CharlesStOlive\FilamentOrchestrator\Filament\Split;

use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;

/**
 * Donne à une page Filament (édition, création, page libre…) un volet latéral :
 * la page devient deux blocs côte à côte — son contenu habituel, et un volet
 * d'un tiers qu'on ouvre à la demande — séparés par une barre que l'on glisse.
 *
 * Ce n'est pas un template Blade de remplacement : Filament compose le contenu
 * d'une page à partir d'un schéma (`content()`), et aucun point d'accroche
 * (render hook) ne peut *envelopper* ce contenu, seulement s'y insérer. Le trait
 * enveloppe donc le schéma de la page dans un SplitView, et laisse tout le reste
 * — en-tête, actions, modales, relation managers — au gabarit de Filament. Une
 * page qui ne déclare aucun volet est rendue exactement comme avant.
 *
 * La page dit ce qu'elle peut accueillir, et le bouton qui l'ouvre se pose où
 * elle veut :
 *
 *     use HasSidePane;
 *
 *     protected function getSidePanes(): array
 *     {
 *         return [MediaLibrarySidePane::NAME => MediaLibrarySidePane::forOrchestration($this->record)];
 *     }
 *
 *     protected function getHeaderActions(): array
 *     {
 *         return [$this->getSidePanes()[MediaLibrarySidePane::NAME]->toggleAction()];
 *     }
 *
 * Le volet n'a aucune raison de dépendre d'une automatisation : le trait ne
 * connaît que Filament, il sert donc aussi à un orchestrateur sans automatisation.
 */
trait HasSidePane
{
    /**
     * Le nom du volet ouvert, `null` quand il est fermé. Verrouillé : le
     * navigateur ne le pose pas lui-même, il passe par `openSidePane()`, qui ne
     * connaît que les volets que la page a déclarés.
     */
    #[Locked]
    public ?string $sidePane = null;

    /**
     * Ce que la page dit du travail en cours à chaque volet, par nom de volet :
     * pour la bibliothèque, les tags de la journée qu'on édite. Il survit à la
     * fermeture du volet, si bien qu'un volet qui s'ouvre plus tard sait déjà de
     * quoi il s'agit. Verrouillé, comme `$sidePane` ; voir SidePaneEvent.
     *
     * @var array<string, array<string, mixed>>
     */
    #[Locked]
    public array $sidePaneContexts = [];

    /**
     * Les volets que cette page peut accueillir, par nom. Vide, la page reste
     * telle que Filament la compose.
     *
     * @return array<string, SidePane>
     */
    protected function getSidePanes(): array
    {
        return [];
    }

    /** Le volet ouvert dès l'affichage de la page. Aucun par défaut : le volet est fermé au lancement. */
    protected function getDefaultSidePane(): ?string
    {
        return null;
    }

    /** La largeur du volet, en % de la page : sa valeur par défaut, puis les bornes du glissement. */
    protected function getSidePaneWidth(): int
    {
        return 33;
    }

    protected function getSidePaneMinWidth(): int
    {
        return 20;
    }

    protected function getSidePaneMaxWidth(): int
    {
        return 60;
    }

    /**
     * Sous quel nom le navigateur retient la largeur réglée à la main. Par
     * défaut, celle-ci vaut pour toutes les pages de la même ressource.
     */
    protected function getSidePaneStorageKey(): string
    {
        return method_exists($this, 'getResource') ? static::getResource()::getSlug() : static::class;
    }

    /**
     * Livewire appelle `mount{Trait}` après le `mount()` de la page : le volet
     * par défaut ne s'ouvre que s'il existe encore.
     */
    public function mountHasSidePane(): void
    {
        $default = $this->getDefaultSidePane();

        $this->sidePane = array_key_exists((string) $default, $this->getSidePanes()) ? $default : null;
    }

    /**
     * Ouvre ce volet, s'il est déclaré. Avec un contexte, celui-ci remplace le
     * contexte du volet avant l'ouverture ; sans, le volet garde celui qu'il a.
     *
     * @param  array<string, mixed>|null  $context
     */
    public function openSidePane(string $name, ?array $context = null): void
    {
        if (! array_key_exists($name, $this->getSidePanes())) {
            $this->sidePane = null;

            return;
        }

        if ($context !== null) {
            $this->setSidePaneContext($name, $context);
        }

        $wasOpen = $this->sidePane === $name;
        $this->sidePane = $name;

        if (! $wasOpen) {
            $this->dispatch(SidePaneEvent::OPENED, pane: $name, context: $this->getSidePaneContext($name));
        }
    }

    public function closeSidePane(): void
    {
        $closed = $this->sidePane;
        $this->sidePane = null;

        if ($closed !== null) {
            $this->dispatch(SidePaneEvent::CLOSED, pane: $closed);
        }
    }

    /**
     * Dit au volet de quoi il s'agit. Le contexte est retenu même volet fermé, et
     * annoncé (CONTEXT_CHANGED) pour que le volet ouvert le suive sur place, sans
     * être remonté — il garde ses filtres et sa sélection.
     *
     * @param  array<string, mixed>  $context
     */
    public function setSidePaneContext(string $name, array $context): void
    {
        if (! array_key_exists($name, $this->getSidePanes()) || ($this->sidePaneContexts[$name] ?? null) === $context) {
            return;
        }

        $this->sidePaneContexts[$name] = $context;
        $this->dispatch(SidePaneEvent::CONTEXT_CHANGED, pane: $name, context: $context);
    }

    /** @return array<string, mixed> */
    public function getSidePaneContext(string $name): array
    {
        return $this->sidePaneContexts[$name] ?? [];
    }

    /**
     * Les demandes que les composants de la page (ou le navigateur) adressent au
     * volet, par événement : voir SidePaneEvent.
     *
     * @param  array<string, mixed>|null  $context
     */
    #[On(SidePaneEvent::OPEN)]
    public function onSidePaneOpenRequested(string $pane, ?array $context = null): void
    {
        $this->openSidePane($pane, $context);
    }

    #[On(SidePaneEvent::CLOSE)]
    public function onSidePaneCloseRequested(): void
    {
        $this->closeSidePane();
    }

    /** @param  array<string, mixed>  $context */
    #[On(SidePaneEvent::CONTEXT)]
    public function onSidePaneContextRequested(string $pane, array $context = []): void
    {
        $this->setSidePaneContext($pane, $context);
    }

    /** Ouvre le volet, ou le ferme s'il est déjà ouvert : le geste d'un bouton d'en-tête. */
    public function toggleSidePane(string $name): void
    {
        $this->isSidePaneOpen($name) ? $this->closeSidePane() : $this->openSidePane($name);
    }

    /** Ce volet est-il ouvert ? Sans nom : un volet quelconque l'est-il ? */
    public function isSidePaneOpen(?string $name = null): bool
    {
        $open = $this->getOpenSidePane();

        return $open !== null && ($name === null || $open->getName() === $name);
    }

    /** Le volet ouvert, à condition que la page le déclare toujours. */
    protected function getOpenSidePane(): ?SidePane
    {
        return $this->sidePane === null ? null : ($this->getSidePanes()[$this->sidePane] ?? null);
    }

    /**
     * Le contenu de la page, enveloppé dans le SplitView. Le contenu d'origine
     * est construit par Filament comme d'habitude (`parent::content()`), sur un
     * schéma à part que le SplitView reçoit comme bloc principal.
     */
    public function content(Schema $schema): Schema
    {
        if ($this->getSidePanes() === []) {
            return parent::content($schema);
        }

        return $schema->components([
            SplitView::make()
                ->main(parent::content($this->makeSchema()))
                ->pane(fn (): ?array => $this->getOpenSidePane()?->getSchema($this->getSidePaneContext($this->sidePane)))
                ->paneKey(fn (): ?string => $this->sidePane)
                ->paneHeading(
                    fn (): ?string => $this->getOpenSidePane()?->getLabel(),
                    fn (): ?string => $this->getOpenSidePane()?->getIcon(),
                )
                ->paneWidth($this->getSidePaneWidth(), $this->getSidePaneMinWidth(), $this->getSidePaneMaxWidth())
                ->storageKey($this->getSidePaneStorageKey()),
        ]);
    }

    /**
     * Le volet a besoin de place : on prend toute la largeur de l'écran plutôt
     * que la largeur maximale du panneau, qui laisserait au formulaire une fois
     * le volet ouvert un espace étriqué. Une page qui ne déclare aucun volet
     * garde la largeur de Filament.
     */
    public function getMaxContentWidth(): Width|string|null
    {
        return $this->getSidePanes() === [] ? parent::getMaxContentWidth() : Width::Full;
    }
}
