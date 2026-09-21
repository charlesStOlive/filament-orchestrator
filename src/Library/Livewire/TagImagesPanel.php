<?php

namespace CharlesStOlive\FilamentOrchestrator\Library\Livewire;

use CharlesStOlive\FilamentOrchestrator\Filament\Split\SidePaneEvent;
use CharlesStOlive\FilamentOrchestrator\Library\Filament\MediaLibraryAction;
use CharlesStOlive\FilamentOrchestrator\Library\Filament\MediaLibrarySidePane;
use CharlesStOlive\FilamentOrchestrator\Library\Filament\MediaUploadAction;
use CharlesStOlive\FilamentOrchestrator\Library\LibraryImageEvent;
use CharlesStOlive\FilamentOrchestrator\Library\LibraryImages;
use CharlesStOlive\FilamentOrchestrator\Models\LibraryMedia;
use CharlesStOlive\FilamentOrchestrator\Models\Orchestration;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
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
 * ces tags, et une case « + » qui ouvre la bibliothèque complète pour en
 * rattacher d'autres — ou en envoyer, la bibliothèque étiquetant ce qu'elle reçoit.
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

    /**
     * Où s'ouvre la bibliothèque : dans une modale (par défaut), ou dans le volet
     * latéral de la page qui accueille ce panneau, réglé sur ces tags. Le volet
     * suppose une page qui en déclare un (HasSidePane) : le panneau le lui demande
     * par événement, sans la connaître.
     */
    #[Locked]
    public bool $libraryInSidePane = false;

    /**
     * Un ensemble d'une seule image (l'image de « une » d'une période, par exemple) : ajouter une image remplace
     * celle qui y est, on ne réordonne rien et les images n'y ont ni numéro ni clé de référence — le texte d'un
     * contenu ne les désigne pas.
     */
    #[Locked]
    public bool $single = false;

    /**
     * Les tags de l'image de « une » de l'ensemble que ce panneau montre. Quand il y en a, la première image
     * porte l'étoile tant qu'aucune n'est choisie : c'est elle qui en tient lieu.
     *
     * @var array<int, string>
     */
    #[Locked]
    public array $coverTags = [];

    /**
     * Les tags sur lesquels la bibliothèque s'ouvre, quand ce ne sont pas ceux du panneau (l'image de « une »
     * se choisit dans la bibliothèque de la période, pas dans un ensemble à part).
     *
     * @var array<int, string>
     */
    #[Locked]
    public array $libraryTags = [];

    /**
     * @param  array<int, string>  $tags
     * @param  array<int, string>  $coverTags
     * @param  array<int, string>  $libraryTags
     */
    public function mount(
        int $orchestrationId,
        array $tags = [],
        string $heading = 'Images',
        bool $libraryInSidePane = false,
        bool $single = false,
        array $coverTags = [],
        array $libraryTags = [],
    ): void {
        $this->orchestrationId = $orchestrationId;
        $this->tags = array_values(array_filter($tags, 'is_string'));
        $this->heading = $heading;
        $this->libraryInSidePane = $libraryInSidePane;
        $this->single = $single;
        $this->coverTags = array_values(array_filter($coverTags, 'is_string'));
        $this->libraryTags = array_values(array_filter($libraryTags, 'is_string'));

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
        $images = (new LibraryImages)->tagged($this->orchestration, $this->tags);

        return $this->single ? $images->take(1)->values() : $images;
    }

    /**
     * La place de chaque image dans son genre, à partir de 1 : « image 2 » est la deuxième image, « vidéo 1 » la
     * première vidéo — les vidéos se numérotent à part, comme le fait le carnet.
     *
     * @return array<int, int> clé de l'image => place
     */
    #[Computed]
    public function positions(): array
    {
        $counters = ['image' => 0, 'video' => 0];
        $positions = [];

        foreach ($this->images as $media) {
            $positions[$media->getKey()] = ++$counters[$media->kind()];
        }

        return $positions;
    }

    /**
     * La première image (une vidéo n'a pas de vignette : elle ne fait pas de couverture) tient lieu d'image de « une » :
     * il y en a une à désigner, et aucune n'est choisie. Null sinon.
     */
    #[Computed]
    public function fallbackCoverId(): ?int
    {
        if ($this->single || $this->coverTags === [] || (new LibraryImages)->tagged($this->orchestration, $this->coverTags)->isNotEmpty()) {
            return null;
        }

        return $this->images->first(fn (LibraryMedia $media): bool => $media->isImage())?->getKey();
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

    /**
     * Une image glissée depuis la bibliothèque et déposée dans la case de fin :
     * elle rejoint cet ensemble, à la suite de la dernière. Une clé qui n'est pas
     * celle d'une image de ce voyage est ignorée, comme une image déjà présente.
     */
    public function attachMedia(int $media): void
    {
        $image = $this->tags === [] ? null : $this->orchestration->libraryMedia()->find($media);

        if ($image === null) {
            return;
        }

        if ($this->single) {
            // Une couverture est une image : une vidéo n'a pas de vignette.
            if ($image->isVideo()) {
                Notification::make()->warning()->title('L’image de une doit être une image, pas une vidéo')->send();

                return;
            }

            $current = (new LibraryImages)->tagged($this->orchestration, $this->tags)->first();

            if ($current?->is($image)) {
                return;
            }

            (new LibraryImages)->replace($this->orchestration, $this->tags, $image);
        } elseif (! (new LibraryImages)->append($this->orchestration, $this->tags, $image)) {
            return;
        }

        $this->dispatch(MediaUploadAction::UPDATED_EVENT, orchestrationId: $this->orchestrationId);
    }

    /** Le type de données d'un glisser-déposer d'image de la bibliothèque, pour la vue. */
    public function dragType(): string
    {
        return LibraryImages::DRAG_TYPE;
    }

    /** Le nom de l'événement navigateur « cette image est survolée », pour la vue. */
    public function hoverEvent(): string
    {
        return LibraryImageEvent::HOVER;
    }

    /**
     * Ouvre la bibliothèque : c'est la case « + » qui la déclenche (voir la vue). L'envoi de fichiers se fait dans la
     * bibliothèque elle-même, qui étiquette ce qu'elle reçoit avec les tags de ce panneau.
     */
    public function libraryAction(): Action
    {
        if ($this->libraryInSidePane) {
            return Action::make('library')
                ->label('Ouvrir la bibliothèque')
                ->action(fn () => $this->dispatch(
                    SidePaneEvent::OPEN,
                    pane: MediaLibrarySidePane::NAME,
                    context: ['tags' => $this->libraryTags ?: $this->tags],
                ))
                ->size(Size::Small)
                ->outlined();
        }

        return MediaLibraryAction::make('library')
            ->record($this->orchestration)
            ->focusTags($this->libraryTags ?: $this->tags)
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
