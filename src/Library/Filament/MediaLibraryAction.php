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
 */
class MediaLibraryAction extends Action
{
    /** @var array<int, string>|Closure */
    protected array|Closure $focusTags = [];

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

    public static function getDefaultName(): ?string
    {
        return 'mediaLibrary';
    }

    /**
     * La table sans cadre ni retrait, quand l'application a les macros
     * `borderNone()` et `paddingNone()` sur les sections : elle occupe alors toute
     * la fenêtre au lieu d'y flotter dans un encadré.
     */
    private function withoutFrame(Section $section): Section
    {
        foreach (['borderNone', 'paddingNone'] as $macro) {
            if ($section::hasMacro($macro)) {
                $section->{$macro}();
            }
        }

        return $section;
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
                $this->withoutFrame(Section::make()->schema([
                    MediaLibraryTable::component($record, $this->getFocusTags()),
                ])),
            ]);
    }
}
