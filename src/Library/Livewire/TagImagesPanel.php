<?php

namespace CharlesStOlive\FilamentOrchestrator\Library\Livewire;

use CharlesStOlive\FilamentOrchestrator\Library\Filament\MediaLibraryAction;
use CharlesStOlive\FilamentOrchestrator\Library\Filament\MediaLibrarySidePane;
use CharlesStOlive\FilamentOrchestrator\Library\Filament\MediaUploadAction;
use CharlesStOlive\FilamentOrchestrator\Library\LibraryImageEvent;
use CharlesStOlive\FilamentOrchestrator\Library\LibraryImages;
use CharlesStOlive\FilamentOrchestrator\Models\LibraryMedia;
use CharlesStOlive\FilamentOrchestrator\Models\Orchestration;
use CharlesStOlive\FilamentUi\Split\SidePaneEvent;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\Width;
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
     * Faux : la bibliothèque s'ouvre sans être « au service » d'aucun tag — ni « Ajouter à », ni envoi étiqueté. C'est le
     * cas d'un ensemble d'une image qu'une action de la bibliothèque désigne elle-même (le croquis d'un carnet, par
     * exemple) : y rattacher des images par lot le romprait.
     */
    #[Locked]
    public bool $libraryFocused = true;

    /**
     * Les tags sur lesquels la bibliothèque s'ouvre déjà filtrée (voir MediaLibraryAction::filterTags()) : les croquis,
     * par exemple — dans une modale comme dans le volet latéral (qui reçoit le filtre dans son contexte).
     *
     * @var array<int, string>
     */
    #[Locked]
    public array $libraryFilterTags = [];

    /**
     * L'identifiant d'une page d'aide de la base de connaissances (Guava), que le « ? » du titre ouvre dans une fenêtre :
     * un simple lien « #modal-… », que l'extension de la base intercepte dans le panneau. Sans elle, il ne mène nulle part.
     */
    #[Locked]
    public ?string $help = null;

    /** Le libellé du lien d'aide (« Aide » par défaut) : l'infobulle du « ? », ou son texte avec `$helpFullTitle`. */
    #[Locked]
    public ?string $helpLabel = null;

    /** Le libellé en toutes lettres au bout de la ligne, plutôt que le « ? » seul juste après le titre. */
    #[Locked]
    public bool $helpFullTitle = false;

    /**
     * @param  array<int, string>  $tags
     * @param  array<int, string>  $coverTags
     * @param  array<int, string>  $libraryTags
     * @param  array<int, string>  $libraryFilterTags
     */
    public function mount(
        int $orchestrationId,
        array $tags = [],
        string $heading = 'Images',
        bool $libraryInSidePane = false,
        bool $single = false,
        array $coverTags = [],
        array $libraryTags = [],
        ?string $help = null,
        ?string $helpLabel = null,
        bool $helpFullTitle = false,
        bool $libraryFocused = true,
        array $libraryFilterTags = [],
    ): void {
        $this->orchestrationId = $orchestrationId;
        $this->tags = array_values(array_filter($tags, 'is_string'));
        $this->heading = $heading;
        $this->libraryInSidePane = $libraryInSidePane;
        $this->single = $single;
        $this->coverTags = array_values(array_filter($coverTags, 'is_string'));
        $this->libraryTags = array_values(array_filter($libraryTags, 'is_string'));
        $this->help = $help;
        $this->helpLabel = $helpLabel;
        $this->helpFullTitle = $helpFullTitle;
        $this->libraryFocused = $libraryFocused;
        $this->libraryFilterTags = array_values(array_filter($libraryFilterTags, 'is_string'));

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

    /** Une image du panneau dont les conversions sont encore en file d'attente : le panneau se redessine (voir la vue). */
    #[Computed]
    public function hasOptimizingMedia(): bool
    {
        return $this->images->contains(fn (LibraryMedia $media): bool => $media->isOptimizing());
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
     * Des fichiers glissés depuis la bibliothèque et déposés dans la case de fin : ils rejoignent cet ensemble, à la suite
     * de la dernière, dans l'ordre où on les a glissés. Une clé qui n'est pas celle d'un fichier de ce voyage est
     * ignorée, comme un fichier déjà présent.
     *
     * Dans un ensemble d'une seule image (l'image de « une »), seul le premier fichier compte, et une vidéo n'est pas
     * une image : elle est refusée.
     *
     * Faux quand rien n'est pris : aucun fichier, ou une vidéo pour une image (le panneau le dit).
     *
     * @param  int|array<int, int|string>  $media  Une clé, ou la liste de celles qu'on a glissées.
     */
    public function attachMedia(int|array $media): bool
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $media), fn (int $id): bool => $id > 0)));

        if ($this->tags === [] || $ids === []) {
            return false;
        }

        $images = new LibraryImages;
        $changed = false;

        if ($this->single) {
            $image = $this->orchestration->libraryMedia()->find($ids[0]);

            if ($image === null) {
                return false;
            }

            // Une couverture est une image : une vidéo n'a pas de vignette.
            if ($image->isVideo()) {
                Notification::make()->warning()->title('L’image de une doit être une image, pas une vidéo')->send();

                return false;
            }

            if (! $images->tagged($this->orchestration, $this->tags)->first()?->is($image)) {
                $images->replace($this->orchestration, $this->tags, $image);
                $changed = true;
            }
        } else {
            foreach ($ids as $id) {
                $image = $this->orchestration->libraryMedia()->find($id);

                if ($image !== null && $images->append($this->orchestration, $this->tags, $image)) {
                    $changed = true;
                }
            }
        }

        if ($changed) {
            $this->dispatch(MediaUploadAction::UPDATED_EVENT, orchestrationId: $this->orchestrationId);
        }

        return true;
    }

    /**
     * Des fichiers choisis dans la bibliothèque que ce panneau a ouverte en fenêtre (voir libraryAction()) : ils le
     * rejoignent comme s'ils y avaient été glissés, puis la fenêtre se ferme. Refusés (une vidéo pour une image de
     * « une »), la fenêtre reste ouverte, pour en choisir un autre.
     *
     * @param  array<int, int|string>  $media
     */
    #[On(MediaLibraryTable::PICKED_EVENT)]
    public function picked(string $picker, array $media): void
    {
        if ($picker !== $this->getId()) {
            return;
        }

        if ($this->attachMedia($media)) {
            $this->unmountAction();
        }
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
                    context: array_filter([
                        'tags' => $this->libraryTags ?: $this->tags,
                        'filterTags' => $this->libraryFilterTags,
                    ]),
                ))
                ->size(Size::Small)
                ->outlined();
        }

        // En fenêtre, la bibliothèque sert à choisir pour ce panneau (voir picked()) : on n'y glisse rien, la fenêtre
        // cache le panneau.
        return MediaLibraryAction::make('library')
            ->record($this->orchestration)
            ->focusTags($this->libraryFocused ? ($this->libraryTags ?: $this->tags) : [])
            ->filterTags($this->libraryFilterTags)
            ->pickFor($this->getId(), many: ! $this->single)
            ->label('Ouvrir la bibliothèque')
            ->size(Size::Small)
            ->outlined();
    }

    /**
     * Le cadrage d'une image (LibraryMedia::FOCUSES) : un clic sur sa vignette ouvre une grille de 3 × 3, la même image
     * dans chaque case, recadrée comme elle le serait de ce côté, une flèche par-dessus. Il ne sert qu'aux affichages
     * qui recadrent l'image (et à sa vignette carrée) ; une vidéo n'a pas de cadrage.
     */
    public function focusAction(): Action
    {
        $media = function (array $arguments): LibraryMedia {
            $media = $this->orchestration->libraryMedia()->findOrFail($arguments['media'] ?? 0);

            abort_if($media->isPlayable(), 404);

            return $media;
        };

        return Action::make('focus')
            ->modalHeading('Cadrage de l’image')
            ->modalDescription('La partie à garder quand l’affichage recadre l’image : en carrousel ou en plein écran, sur une carte, dans une vignette, en fond de page. Une image affichée entière n’en tient pas compte.')
            ->modalWidth(Width::ExtraLarge)
            ->modalSubmitActionLabel('Appliquer')
            ->fillForm(fn (array $arguments): array => ['focus' => $media($arguments)->focus()])
            ->schema(fn (array $arguments): array => [
                ViewField::make('focus')
                    ->hiddenLabel()
                    ->view('filament-orchestrator::library.focus-picker')
                    ->viewData([
                        'imageUrl' => $media($arguments)->conversionUrl('medium'),
                        'focuses' => LibraryMedia::FOCUSES,
                    ]),
            ])
            ->action(function (array $data, array $arguments) use ($media): void {
                $media($arguments)->setFocus((string) ($data['focus'] ?? LibraryMedia::FOCUS_DEFAULT));

                $this->dispatch(MediaUploadAction::UPDATED_EVENT, orchestrationId: $this->orchestrationId);
            });
    }

    /** L'icône du cadrage d'une image, pour la vue : null au centre (rien à signaler). */
    public function focusIcon(LibraryMedia $media): ?string
    {
        $focus = $media->focus();

        return $focus === LibraryMedia::FOCUS_DEFAULT ? null : LibraryMedia::FOCUSES[$focus]['icon'];
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
