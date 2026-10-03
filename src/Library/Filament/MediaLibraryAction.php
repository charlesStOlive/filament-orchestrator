<?php

namespace CharlesStOlive\FilamentOrchestrator\Library\Filament;

use Closure;
use CharlesStOlive\FilamentOrchestrator\Library\Livewire\MediaLibraryTable;
use CharlesStOlive\FilamentOrchestrator\Models\Orchestration;
use Filament\Actions\Action;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;

/**
 * Ouvre la bibliothèque d'images d'une orchestration dans une modale.
 *
 * S'utilise comme n'importe quelle action, sur une page d'édition ou ailleurs :
 *
 *     MediaLibraryAction::make()->record($this->record)
 *
 * Pour choisir des fichiers (un champ qui en attend un, ou plusieurs) : `->pickFor($this->getId(), many: true)`, et
 * écouter MediaLibraryTable::PICKED_EVENT (voir TagImagesPanel).
 */
class MediaLibraryAction extends Action
{
    /** @var array<int, string>|Closure */
    protected array|Closure $focusTags = [];

    /** @var array<int, string>|Closure */
    protected array|Closure $filterTags = [];

    protected string|Closure|null $picker = null;

    protected bool|Closure $pickMany = false;

    /**
     * Ouvre la bibliothèque « au service » de ces tags : les images qui les
     * portent se filtrent d'un clic, on peut y rattacher d'autres images par
     * lot, et l'envoi d'images les étiquette d'emblée. C'est ce qui fait de
     * la bibliothèque le sélecteur d'images d'une journée.
     *
     * @param  array<int, string>|Closure  $tags
     */
    public function focusTags(array|Closure $tags): static
    {
        $this->focusTags = $tags;

        return $this;
    }

    /** @return array<int, string> */
    public function getFocusTags(): array
    {
        return array_values((array) $this->evaluate($this->focusTags));
    }

    /**
     * Ouvre la bibliothèque déjà filtrée sur ces tags (le filtre « Tags ») : les croquis, par exemple, quand on
     * l'ouvre pour en choisir un. Le filtre se retire comme un autre ; rien n'est étiqueté.
     *
     * @param  array<int, string>|Closure  $tags
     */
    public function filterTags(array|Closure $tags): static
    {
        $this->filterTags = $tags;

        return $this;
    }

    /** @return array<int, string> */
    public function getFilterTags(): array
    {
        return array_values((array) $this->evaluate($this->filterTags));
    }

    /**
     * Ouvre la bibliothèque pour y choisir des fichiers, au profit de `$picker` (un nom : celui du composant Livewire qui
     * les attend, par exemple) : elle les annonce par MediaLibraryTable::PICKED_EVENT, avec ce nom, et c'est à lui de
     * les prendre — et de fermer la fenêtre. Un seul fichier attendu, un clic sur une carte le choisit ; plusieurs
     * (`$many`), un clic coche, et « Insérer la sélection » les envoie.
     */
    public function pickFor(string|Closure|null $picker, bool|Closure $many = false): static
    {
        $this->picker = $picker;
        $this->pickMany = $many;

        return $this;
    }

    public function getPicker(): ?string
    {
        $picker = $this->evaluate($this->picker);

        return filled($picker) ? (string) $picker : null;
    }

    public function shouldPickMany(): bool
    {
        return (bool) $this->evaluate($this->pickMany);
    }

    public static function getDefaultName(): ?string
    {
        return 'mediaLibrary';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Bibliothèque')
            ->icon('heroicon-o-photo')
            ->color('gray')
            ->modalHeading('Bibliothèque d’images')
            ->modalWidth(Width::SevenExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fermer')
            ->schema(fn (Orchestration $record): array => [
                // Sans cadre ni retrait (macros de filament-ui) : la table occupe toute la fenêtre au lieu d'y
                // flotter dans un encadré.
                Section::make()->borderNone()->paddingNone()->schema([
                    MediaLibraryTable::component(
                        $record,
                        $this->getFocusTags(),
                        filterTags: $this->getFilterTags(),
                        picker: $this->getPicker(),
                        pickMany: $this->shouldPickMany(),
                    ),
                ]),
            ]);
    }
}
