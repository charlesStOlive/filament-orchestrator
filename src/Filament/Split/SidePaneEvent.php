<?php

namespace CharlesStOlive\FilamentOrchestrator\Filament\Split;

/**
 * Les événements Livewire par lesquels une page à volet latéral (HasSidePane)
 * et ses composants se parlent, sans se connaître.
 *
 * Ce sont des événements Livewire ordinaires : n'importe quel composant de la
 * page — un panneau d'images, la bibliothèque elle-même — les émet avec
 * `$this->dispatch()` et les reçoit avec `#[On()]`. Le trait de la page
 * répond aux demandes et annonce, en retour, ce qui vient de se passer.
 *
 * Demandes, adressées à la page :
 *
 *     $this->dispatch(SidePaneEvent::OPEN, pane: 'library', context: ['tags' => ['day:abc']]);
 *     $this->dispatch(SidePaneEvent::CONTEXT, pane: 'library', context: ['tags' => []]);
 *     $this->dispatch(SidePaneEvent::CLOSE);
 *
 * Annonces, émises par la page :
 *
 *     #[On(SidePaneEvent::CONTEXT_CHANGED)]
 *     public function follow(string $pane, array $context): void { ... }
 *
 * Le contexte est un tableau libre : c'est ce que la page dit du travail en
 * cours au volet (« on travaille sur cette journée », par ses tags). La page le
 * retient par volet : un volet qui s'ouvre plus tard le trouve déjà là, et un
 * volet déjà ouvert le suit par CONTEXT_CHANGED.
 *
 * Ces événements peuvent aussi être émis depuis le navigateur : la page ne
 * retient donc que ce qui concerne les volets qu'elle a déclarés.
 */
final class SidePaneEvent
{
    /** Demande d'ouvrir un volet (`pane`), avec au besoin son contexte (`context`). */
    public const OPEN = 'side-pane-open';

    /** Demande de fermer le volet ouvert. */
    public const CLOSE = 'side-pane-close';

    /** Demande de changer le contexte d'un volet (`pane`, `context`), qu'il soit ouvert ou non. */
    public const CONTEXT = 'side-pane-context';

    /** Annonce : un volet vient de s'ouvrir (`pane`, `context`). */
    public const OPENED = 'side-pane-opened';

    /** Annonce : le volet vient de se fermer (`pane`). */
    public const CLOSED = 'side-pane-closed';

    /** Annonce : le contexte d'un volet vient de changer (`pane`, `context`). */
    public const CONTEXT_CHANGED = 'side-pane-context-changed';
}
