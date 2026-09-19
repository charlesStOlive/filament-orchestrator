<?php

namespace CharlesStOlive\FilamentOrchestrator\Library\Livewire;

use CharlesStOlive\FilamentOrchestrator\Library\Filament\MediaLibraryAction;
use CharlesStOlive\FilamentOrchestrator\Library\Filament\MediaUploadAction;
use CharlesStOlive\FilamentOrchestrator\Library\LibraryImages;
use CharlesStOlive\FilamentOrchestrator\Models\LibraryMedia;
use CharlesStOlive\FilamentOrchestrator\Models\Orchestration;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Enums\Size;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * La version légère de la bibliothèque, à glisser dans l'écran d'une journée
 * (ou de tout contenu qui lit des images par tag) : les images qui portent
 * ces tags, un envoi qui les étiquette d'emblée, et un accès à la
 * bibliothèque complète pour en rattacher d'autres à la main.
 */
class TagImagesPanel extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    #[Locked]
    public int $orchestrationId;

    /** @var array<int, string> */
    #[Locked]
    public array $tags = [];

    #[Locked]
    public string $heading = 'Images';

    /** @param array<int, string> $tags */
    public function mount(int $orchestrationId, array $tags = [], string $heading = 'Images'): void
    {
        $this->orchestrationId = $orchestrationId;
        $this->tags = array_values(array_filter($tags, 'is_string'));
        $this->heading = $heading;

        // Échoue tôt (404) plutôt qu'à l'affichage.
        $this->orchestration();
    }

    public function boot(): void
    {
        abort_unless(auth()->check(), 403);
    }

    /** L'icône de l'image d'en-tête, pour la vue. */
    public function headerIcon(): string
    {
        return LibraryImages::HEADER_ICON;
    }

    /** Un envoi ou un rattachement terminé ailleurs : recevoir l'événement rafraîchit les miniatures. */
    #[On(MediaUploadAction::UPDATED_EVENT)]
    public function refreshImages(): void {}

    #[Computed]
    public function orchestration(): Orchestration
    {
        return Orchestration::query()->findOrFail($this->orchestrationId);
    }

    /** @return Collection<int, LibraryMedia> */
    #[Computed]
    public function images(): Collection
    {
        return (new LibraryImages)->tagged($this->orchestration, $this->tags);
    }

    /**
     * Range les images dans l'ordre où on vient de les déposer. La première est
     * l'en-tête. Des identifiants étrangers à cet ensemble sont ignorés.
     *
     * @param  array<int, int|string>  $ids
     */
    public function reorder(array $ids): void
    {
        (new LibraryImages)->reorder($this->orchestration, $this->tags, $ids);

        $this->dispatch(MediaUploadAction::UPDATED_EVENT, orchestrationId: $this->orchestrationId);
    }

    public function uploadAction(): Action
    {
        return MediaUploadAction::make('upload')
            ->record($this->orchestration)
            ->tags($this->tags)
            ->source('panel')
            ->size(Size::Small)
            ->outlined();
    }

    public function libraryAction(): Action
    {
        return MediaLibraryAction::make('library')
            ->record($this->orchestration)
            ->focusTags($this->tags)
            ->label('Ouvrir la bibliothèque')
            ->size(Size::Small)
            ->outlined();
    }

    /** Retire l'image de cet ensemble, sans la supprimer : elle reste dans la bibliothèque. */
    public function detachAction(): Action
    {
        return Action::make('detach')
            ->label('Retirer de la sélection')
            ->action(function (array $arguments): void {
                $media = $this->orchestration->libraryMedia()->findOrFail($arguments['media'] ?? 0);

                $media->detachTags($this->tags, $this->orchestration->libraryTagType());

                $this->dispatch(MediaUploadAction::UPDATED_EVENT, orchestrationId: $this->orchestrationId);
            });
    }

    public function render(): View
    {
        return view('filament-orchestrator::livewire.tag-images-panel');
    }
}
