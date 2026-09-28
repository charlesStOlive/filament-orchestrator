<?php

namespace CharlesStOlive\FilamentOrchestrator\Library\Filament;

use CharlesStOlive\FilamentOrchestrator\Library\Livewire\MediaLibraryTable;
use CharlesStOlive\FilamentOrchestrator\Models\Orchestration;
use CharlesStOlive\FilamentUi\Split\SidePane;

/**
 * La bibliothèque d'images d'une orchestration, dans le volet latéral d'une
 * page (voir HasSidePane) plutôt que dans une modale : elle reste à côté du
 * formulaire, qu'on peut continuer à utiliser.
 *
 *     MediaLibrarySidePane::NAME => MediaLibrarySidePane::forOrchestration($this->record)
 *
 * C'est le même composant que MediaLibraryAction ouvre en modale — un tri, un
 * filtre ou un envoi se comportent à l'identique. Son contexte est
 * `['tags' => [...]]` : les tags du contenu sur lequel la page travaille (une
 * journée), que la bibliothèque affiche, propose en filtre et applique aux envois.
 * `filterTags`, facultatif, pose en plus le filtre « Tags » (voir MediaLibraryAction::filterTags()) : les
 * croquis, par exemple, quand on ouvre le volet pour en choisir un. `filterDates` (`from` / `until`), de même, pose le
 * filtre « Date de prise de vue ».
 */
class MediaLibrarySidePane extends SidePane
{
    public const NAME = 'library';

    public static function forOrchestration(Orchestration $orchestration): static
    {
        return static::make(self::NAME)
            ->label('Bibliothèque')
            ->icon('heroicon-o-photo')
            // Le contexte de la page dit sur quoi elle travaille (les tags de la journée
            // ouverte) : la bibliothèque s'ouvre déjà réglée dessus, et la suit ensuite.
            ->schema(fn (array $context): array => [
                MediaLibraryTable::component($orchestration, (array) ($context['tags'] ?? []), followsSidePane: true, filterTags: (array) ($context['filterTags'] ?? []), filterDates: (array) ($context['filterDates'] ?? [])),
            ]);
    }
}
