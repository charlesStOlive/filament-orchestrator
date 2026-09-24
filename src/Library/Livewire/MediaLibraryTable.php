<?php

namespace CharlesStOlive\FilamentOrchestrator\Library\Livewire;

use Carbon\CarbonImmutable;
use CharlesStOlive\FilamentOrchestrator\Library\Filament\MediaLibrarySidePane;
use CharlesStOlive\FilamentOrchestrator\Library\Filament\MediaUploadAction;
use CharlesStOlive\FilamentOrchestrator\Library\LibraryAction;
use CharlesStOlive\FilamentOrchestrator\Library\LibraryContext;
use CharlesStOlive\FilamentOrchestrator\Library\LibraryImages;
use CharlesStOlive\FilamentOrchestrator\Library\LibraryIngestor;
use CharlesStOlive\FilamentOrchestrator\Library\MediaMetadata;
use CharlesStOlive\FilamentOrchestrator\Library\TagLabels;
use CharlesStOlive\FilamentOrchestrator\Library\YoutubeUrl;
use CharlesStOlive\FilamentOrchestrator\Models\LibraryMedia;
use CharlesStOlive\FilamentOrchestrator\Models\LibraryTag;
use CharlesStOlive\FilamentOrchestrator\Models\Orchestration;
use CharlesStOlive\FilamentUi\Split\SidePaneEvent;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group as SchemaGroup;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View as ViewComponent;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Filament\Tables\TableComponent;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Session;

/**
 * Gestion de la bibliothèque d'images d'une orchestration : une grille de
 * cartes que l'on trie, filtre, groupe et étiquette par lots.
 *
 * C'est un composant Livewire autonome : il se glisse dans une modale, un
 * slide-over ou une page (voir MediaLibraryAction). Il ne sait rien des
 * journées d'un voyage — les tags qu'il manipule sont opaques, et leur
 * libellé vient des LibraryTagLabeler configurés.
 */
class MediaLibraryTable extends TableComponent
{
    /** Verrouillé : le navigateur ne doit pas pouvoir pointer vers la bibliothèque d'un autre voyage. */
    #[Locked]
    public int $orchestrationId;

    /**
     * Tags « au service » desquels la bibliothèque est ouverte (ceux d'une
     * journée, par exemple) : l'envoi les pose, et des actions par lot y
     * rattachent ou en retirent des images. Vide, c'est la bibliothèque seule.
     *
     * @var array<int, string>
     */
    #[Locked]
    public array $focusTags = [];

    /**
     * Vrai quand la bibliothèque est celle du volet latéral d'une page : elle
     * change alors de journée sur place, quand la page annonce un nouveau contexte
     * (voir SidePaneEvent), au lieu d'être remontée — elle garde ses filtres et sa
     * sélection. Dans une modale, elle garde les tags avec lesquels on l'a ouverte.
     */
    #[Locked]
    public bool $followsSidePane = false;

    /**
     * La taille des vignettes : S, M ou L. Gardée dans la session de
     * l'utilisateur, elle survit à la fermeture de la bibliothèque.
     */
    #[Session(key: 'orchestrator-library-size')]
    #[Locked]
    public string $size = 'm';

    /**
     * Vrai quand l'utilisateur a figé l'affichage des images entières (« fit »)
     * au lieu de les recadrer. Faux, l'image n'est entière qu'au survol de sa
     * carte. Gardé dans la session, comme la taille.
     */
    #[Session(key: 'orchestrator-library-fit')]
    #[Locked]
    public bool $fit = false;

    /**
     * Les trois tailles. `grid` est le nombre de cartes par ligne selon la
     * largeur de la *bibliothèque* (les clés en « @ » sont des requêtes de
     * conteneur, voir la vue) et non celle de l'écran : dans le volet d'un tiers
     * d'une page, la grille se resserre comme dans une modale étroite. `tags` est
     * le nombre de tags nommés sur la carte avant le « +N ».
     *
     * @var array<string, array{label: string, hint: string, grid: array<string, int>, tags: int}>
     */
    private const SIZES = [
        's' => ['label' => 'S', 'hint' => 'Petites vignettes, en icônes', 'grid' => ['default' => 4, '@sm' => 5, '@2xl' => 8, '@4xl' => 10], 'tags' => 0],
        'm' => ['label' => 'M', 'hint' => 'Vignettes normales', 'grid' => ['default' => 2, '@sm' => 3, '@2xl' => 4, '@4xl' => 5], 'tags' => 1],
        'l' => ['label' => 'L', 'hint' => 'Grandes vignettes', 'grid' => ['default' => 1, '@2xl' => 2, '@4xl' => 3], 'tags' => 2],
    ];

    /**
     * Le composant tel qu'on le glisse dans un schéma Filament — modale
     * (MediaLibraryAction), volet latéral (MediaLibrarySidePane) ou page.
     *
     * En modale, la clé change avec les tags de service : un autre contexte
     * remonte la bibliothèque à neuf. Dans le volet, la clé est fixe — la
     * bibliothèque suit les changements de journée par événement.
     *
     * @param  array<int, string>  $focusTags
     */
    public static function component(Orchestration $orchestration, array $focusTags = [], bool $followsSidePane = false): Livewire
    {
        return Livewire::make(static::class, [
            'orchestrationId' => $orchestration->getKey(),
            'focusTags' => $focusTags,
            'followsSidePane' => $followsSidePane,
        ])->key($followsSidePane
            ? 'media-library-pane-'.$orchestration->getKey()
            : 'media-library-'.$orchestration->getKey().'-'.md5(implode('|', $focusTags)));
    }

    /** @param array<int, string> $focusTags */
    public function mount(int $orchestrationId, array $focusTags = [], bool $followsSidePane = false): void
    {
        $this->orchestrationId = $orchestrationId;
        $this->focusTags = array_values(array_filter($focusTags, 'is_string'));
        $this->followsSidePane = $followsSidePane;

        // Échoue tôt (404) plutôt qu'à l'affichage de la table.
        $this->orchestration();
    }

    /**
     * La page annonce sur quoi elle travaille (les tags de la journée ouverte) :
     * la bibliothèque du volet s'y règle, à la place — « Ajouter à », le filtre
     * « Seulement » et l'étiquetage des envois suivent la journée.
     *
     * @param  array<string, mixed>  $context
     */
    #[On(SidePaneEvent::CONTEXT_CHANGED)]
    public function followSidePaneContext(string $pane, array $context = []): void
    {
        if (! $this->followsSidePane || $pane !== MediaLibrarySidePane::NAME) {
            return;
        }

        $this->focusTags = array_values(array_filter((array) ($context['tags'] ?? []), 'is_string'));

        // Ce que l'on a calculé des anciens tags est périmé.
        unset($this->libraryContext, $this->focusLabel);
    }

    public function boot(): void
    {
        abort_unless(auth()->check(), 403);
    }

    /** Un envoi terminé ailleurs : le simple fait de recevoir l'événement rafraîchit la grille. */
    #[On(MediaUploadAction::UPDATED_EVENT)]
    public function refreshLibrary(): void {}

    /** Dans quelle bibliothèque, et au service de quoi, les actions s'exécutent. */
    #[Computed]
    public function libraryContext(): LibraryContext
    {
        return new LibraryContext($this->orchestration, $this->focusTags);
    }

    #[Computed]
    public function orchestration(): Orchestration
    {
        return Orchestration::query()->findOrFail($this->orchestrationId);
    }

    /**
     * Tags de la bibliothèque, prêts pour un Select : nom technique => libellé.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function tagOptions(): array
    {
        return LibraryTag::query()
            ->where('type', $this->orchestration->libraryTagType())
            ->get()
            ->mapWithKeys(fn (LibraryTag $tag): array => [$tag->name => $this->tagLabel($tag->name)])
            ->sort()
            ->all();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->orchestration->libraryMedia()->getQuery()->with('tags'))
            ->columns([
                // Toute la carte est dessinée par cette vue : l'image en fond, sous
                // la case à cocher, et par-dessus la date, la position et les
                // tags. La colonne porte le nom de la date pour rendre la grille
                // triable par date de prise de vue. Le Stack, même à un seul
                // enfant, est ce qui fait de Filament une grille de cartes plutôt
                // qu'un tableau.
                Stack::make([
                    ViewColumn::make('taken_at')
                        ->label('Date de prise de vue')
                        ->sortable()
                        ->view('filament-orchestrator::library.thumbnail')
                        ->viewData(fn (LibraryMedia $record): array => [
                            'size' => $this->currentSize(),
                            'fit' => $this->fit,
                            'orchestrationId' => $this->orchestrationId,
                            'dragType' => LibraryImages::DRAG_TYPE,
                            'tags' => $this->visibleTags($record),
                            'tagLabels' => $this->tagLabels($record),
                            'marks' => $this->marksOf($record),
                        ]),
                    // Ne dessine rien : elle est là pour que « Trier par » propose le type (images, puis vidéos).
                    ViewColumn::make('mime_type')
                        ->label('Type')
                        ->sortable()
                        ->view('filament-orchestrator::library.empty'),
                ]),
            ])
            // Au service de quoi la bibliothèque est ouverte : dans l'en-tête de la table, donc dans le bloc qui reste
            // visible quand la grille défile (voir la vue).
            ->header(fn (): ?View => $this->focusTags === []
                ? null
                : view('filament-orchestrator::library.focus-banner', ['label' => $this->focusLabel]))
            ->contentGrid(fn (): array => self::SIZES[$this->currentSize()]['grid'])
            ->defaultSort('taken_at')
            ->paginated([24, 48, 96])
            ->defaultPaginationPageOption(48)
            // Tout tient sur une ligne : les filtres et le groupement sont des
            // menus déroulants de la barre d'outils, à côté de l'envoi et de
            // la sélection. La grille n'ajoute que sa ligne de tri.
            ->filters($this->filters())
            ->filtersFormColumns(2)
            ->filtersFormWidth(Width::TwoExtraLarge)
            ->groupingSettingsInDropdownOnDesktop()
            ->groups([
                $this->kindGroup(),
                $this->dateGroup(),
                $this->zoneGroup('zone_fine', 'Zone (environ 1 km)', 2),
                $this->zoneGroup('zone_large', 'Zone (environ 10 km)', 1),
            ])
            ->recordActions([$this->editAction(), $this->deleteImageAction()])
            ->recordAction('edit')
            ->selectable()
            ->toolbarActions([
                MediaUploadAction::make('upload')
                    ->record($this->orchestration)
                    ->tags($this->focusTags)
                    ->source('library')
                    ->label('Charger des images')
                    ->size(Size::Small),
                ...$this->shortcutActions(),
                $this->selectionActions(),
                $this->sizeActions(),
                $this->fitAction(),
            ])
            ->emptyStateHeading('Aucune image')
            ->emptyStateDescription('Les images envoyées pour ce voyage apparaissent ici.')
            ->emptyStateIcon('heroicon-o-photo');
    }

    public function render(): View
    {
        return view('filament-orchestrator::livewire.media-library-table');
    }

    /*
    |--------------------------------------------------------------------------
    | Filtres
    |--------------------------------------------------------------------------
    */

    /** @return array<int, \Filament\Tables\Filters\BaseFilter> */
    private function filters(): array
    {
        return [
            ...$this->focusFilters(),

            // Images, vidéos, YouTube ou images externes : reconnus à leur type de fichier et à leur origine.
            SelectFilter::make('kind')
                ->label('Type')
                ->options([
                    'image' => 'Images',
                    'video' => 'Vidéos',
                    'youtube' => 'YouTube',
                    'external_image' => 'Images externes',
                ])
                ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                    'video' => $query->videos(),
                    'image' => $query->uploadedImages(),
                    'youtube' => $query->youtube(),
                    'external_image' => $query->externalImages(),
                    default => $query,
                }),

            Filter::make('taken_at')
                ->label('Date de prise de vue')
                ->schema([
                    DatePicker::make('from')->label('Du'),
                    DatePicker::make('until')->label('Au'),
                ])
                ->query(fn (Builder $query, array $data): Builder => $query
                    ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('taken_at', '>=', $date))
                    ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('taken_at', '<=', $date)))
                ->indicateUsing(function (array $data): array {
                    $indicators = [];

                    if ($data['from'] ?? null) {
                        $indicators[] = 'Depuis le '.CarbonImmutable::parse($data['from'])->format('d/m/Y');
                    }

                    if ($data['until'] ?? null) {
                        $indicators[] = 'Jusqu’au '.CarbonImmutable::parse($data['until'])->format('d/m/Y');
                    }

                    return $indicators;
                }),

            SelectFilter::make('tags')
                ->label('Tags')
                ->multiple()
                ->options(fn (): array => $this->tagOptions)
                ->query(fn (Builder $query, array $data): Builder => filled($data['values'] ?? null)
                    ? $query->withAnyTags($data['values'], $this->orchestration->libraryTagType())
                    : $query)
                ->indicateUsing(fn (array $data): array => array_map(
                    fn (string $tag): string => 'Tag : '.$this->tagLabel($tag),
                    $data['values'] ?? [],
                )),

            TernaryFilter::make('tagged')
                ->label('Étiquetage')
                ->placeholder('Toutes')
                ->trueLabel('Avec au moins un tag')
                ->falseLabel('Sans aucun tag')
                ->queries(
                    true: fn (Builder $query): Builder => $query->has('tags'),
                    false: fn (Builder $query): Builder => $query->doesntHave('tags'),
                    blank: fn (Builder $query): Builder => $query,
                ),

            TernaryFilter::make('gps')
                ->label('Position GPS')
                ->placeholder('Toutes')
                ->trueLabel('Avec position')
                ->falseLabel('Sans position')
                ->queries(
                    true: fn (Builder $query): Builder => $query->withGps(),
                    false: fn (Builder $query): Builder => $query->where(
                        fn (Builder $query): Builder => $query->whereNull('latitude')->orWhereNull('longitude'),
                    ),
                    blank: fn (Builder $query): Builder => $query,
                ),

            Filter::make('near')
                ->label('Autour d’un point')
                ->schema([
                    TextInput::make('latitude')->label('Latitude')->numeric()->minValue(-90)->maxValue(90),
                    TextInput::make('longitude')->label('Longitude')->numeric()->minValue(-180)->maxValue(180),
                    Select::make('radius')
                        ->label('Rayon')
                        ->options([1 => '1 km', 5 => '5 km', 20 => '20 km', 50 => '50 km', 200 => '200 km'])
                        ->default(5)
                        ->selectablePlaceholder(false),
                ])
                ->query(fn (Builder $query, array $data): Builder => is_numeric($data['latitude'] ?? null) && is_numeric($data['longitude'] ?? null)
                    ? $query->near((float) $data['latitude'], (float) $data['longitude'], (float) ($data['radius'] ?? 5))
                    : $query)
                ->indicateUsing(fn (array $data): array => is_numeric($data['latitude'] ?? null) && is_numeric($data['longitude'] ?? null)
                    ? ['À '.($data['radius'] ?? 5).' km de '.$data['latitude'].', '.$data['longitude']]
                    : []),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Groupements
    |--------------------------------------------------------------------------
    */

    private function kindGroup(): Group
    {
        return Group::make('mime_type')
            ->id('kind')
            ->label('Type')
            ->titlePrefixedWithLabel(false)
            ->getKeyFromRecordUsing(fn (LibraryMedia $record): string => $this->kindKey($record))
            ->getTitleFromRecordUsing(fn (LibraryMedia $record): string => match ($this->kindKey($record)) {
                'video' => 'Vidéos',
                'youtube' => 'YouTube',
                'external_image' => 'Images externes',
                default => 'Images',
            })
            ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query->orderBy('mime_type', $direction))
            ->scopeQueryByKeyUsing(fn (Builder $query, ?string $key): Builder => match ($key) {
                'video' => $query->videos(),
                'youtube' => $query->youtube(),
                'external_image' => $query->externalImages(),
                default => $query->uploadedImages(),
            });
    }

    /** 'image', 'video', 'youtube' ou 'external_image' : le type au sens du filtre et du groupement « Type ». */
    private function kindKey(LibraryMedia $record): string
    {
        return match (true) {
            $record->isYoutube() => 'youtube',
            $record->isExternalImage() => 'external_image',
            $record->isVideo() => 'video',
            default => 'image',
        };
    }

    private function dateGroup(): Group
    {
        return Group::make('taken_at')
            ->id('date')
            ->label('Date de prise de vue')
            ->titlePrefixedWithLabel(false)
            ->getKeyFromRecordUsing(fn (LibraryMedia $record): ?string => $record->taken_at?->toDateString())
            ->getTitleFromRecordUsing(fn (LibraryMedia $record): string => $record->taken_at
                ? $record->taken_at->locale('fr')->translatedFormat('l j F Y')
                : 'Sans date')
            ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query->orderBy('taken_at', $direction))
            ->scopeQueryByKeyUsing(fn (Builder $query, ?string $key): Builder => blank($key)
                ? $query->whereNull('taken_at')
                : $query->whereDate('taken_at', $key));
    }

    /**
     * Regroupe les images par zone : les coordonnées arrondies à `$decimals`
     * décimales (2 ≈ 1 km, 1 ≈ 10 km). C'est un quadrillage, pas des grappes
     * calculées : deux photos voisines de part et d'autre d'une ligne du
     * quadrillage peuvent tomber dans deux zones.
     */
    private function zoneGroup(string $id, string $label, int $decimals): Group
    {
        $half = 0.5 * 10 ** -$decimals;

        return Group::make('latitude')
            ->id($id)
            ->label($label)
            ->titlePrefixedWithLabel(false)
            ->getKeyFromRecordUsing(fn (LibraryMedia $record): ?string => $record->hasGps()
                ? number_format(round($record->latitude, $decimals), $decimals, '.', '')
                    .','.number_format(round($record->longitude, $decimals), $decimals, '.', '')
                : null)
            ->getTitleFromRecordUsing(fn (LibraryMedia $record): string => $record->hasGps()
                ? 'Zone '.number_format(round($record->latitude, $decimals), $decimals, ',', '')
                    .' ; '.number_format(round($record->longitude, $decimals), $decimals, ',', '')
                : 'Sans position')
            ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query
                ->orderByRaw("ROUND(latitude, {$decimals}) {$direction}")
                ->orderByRaw("ROUND(longitude, {$decimals}) {$direction}"))
            ->scopeQueryByKeyUsing(function (Builder $query, ?string $key) use ($half): Builder {
                if (blank($key)) {
                    return $query->where(fn (Builder $query): Builder => $query->whereNull('latitude')->orWhereNull('longitude'));
                }

                [$latitude, $longitude] = array_map('floatval', explode(',', $key));

                return $query
                    ->where('latitude', '>=', $latitude - $half)->where('latitude', '<', $latitude + $half)
                    ->where('longitude', '>=', $longitude - $half)->where('longitude', '<', $longitude + $half);
            });
    }

    /*
    |--------------------------------------------------------------------------
    | Actions
    |--------------------------------------------------------------------------
    */

    /**
     * La poubelle d'une carte : elle demande toujours confirmation, et dit où
     * l'image s'affichait. Comme l'édition, le bouton de la rangée d'actions
     * n'est pas affiché (la poubelle est posée sur l'image par
     * library/thumbnail, qui monte cette action), mais il doit rester dans le
     * DOM : Filament n'ouvre pas une action masquée.
     */
    private function deleteImageAction(): Action
    {
        return Action::make('deleteImage')
            ->label('Supprimer')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->extraAttributes(['class' => 'hidden'])
            ->requiresConfirmation()
            ->modalHeading(fn (LibraryMedia $record): string => $record->isVideo() ? 'Supprimer cette vidéo ?' : 'Supprimer cette image ?')
            ->modalDescription(fn (LibraryMedia $record): string => $this->deletionDescription($record))
            ->modalSubmitActionLabel('Supprimer')
            ->action(fn (LibraryMedia $record) => $this->deleteMedia($record));
    }

    /** Ce que la suppression emporte : le fichier, et sa place dans chacune des périodes où il est rangé. */
    private function deletionDescription(LibraryMedia $record): string
    {
        $usedBy = $this->tagLabels($record);

        return 'Elle disparaît de la bibliothèque'
            .($usedBy === [] ? '' : ' et de : '.implode(', ', $usedBy))
            .'. Cette action est définitive.';
    }

    private function deleteMedia(LibraryMedia $record): void
    {
        $video = $record->isVideo();
        $record->delete();

        Notification::make()->success()->title($video ? 'Vidéo supprimée' : 'Image supprimée')->send();
        $this->dispatch(MediaUploadAction::UPDATED_EVENT, orchestrationId: $this->orchestrationId);
    }

    /**
     * Le bouton « Supprimer » de la fenêtre d'édition : le même geste que la poubelle de la carte, avec la même
     * confirmation (elle dit où le fichier était rangé), puis la fenêtre se ferme.
     */
    private function deleteFromPopupAction(): Action
    {
        return Action::make('deleteFromPopup')
            ->label('Supprimer')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->outlined()
            ->requiresConfirmation()
            ->modalHeading(fn (LibraryMedia $record): string => $record->isVideo() ? 'Supprimer cette vidéo ?' : 'Supprimer cette image ?')
            ->modalDescription(fn (LibraryMedia $record): string => $this->deletionDescription($record))
            ->modalSubmitActionLabel('Supprimer')
            // La fenêtre d'édition n'a plus de fichier à éditer : elle se ferme avec lui.
            ->cancelParentActions()
            ->action(fn (LibraryMedia $record) => $this->deleteMedia($record));
    }

    /**
     * La fenêtre d'édition d'une image ou d'une vidéo, ouverte par un clic sur sa carte : très large, en deux parties. À
     * gauche (un cinquième) ce qu'on édite — nom, légende, texte alternatif, date, position, tags, et la coupe d'une
     * vidéo — et ce que le serveur sait du fichier ; à droite (quatre cinquièmes) l'image entière, ou la vidéo et son
     * lecteur.
     */
    private function editAction(): Action
    {
        return Action::make('edit')
            ->label('Modifier')
            ->icon('heroicon-o-pencil-square')
            // Toute la carte ouvre cette action, et le crayon est posé sur
            // l'image (library/thumbnail) : le bouton lui-même n'est pas
            // affiché. Il doit rester dans le DOM, Filament n'ouvre pas une
            // action masquée.
            ->extraAttributes(['class' => 'hidden'])
            ->modalHeading(fn (LibraryMedia $record): string => match (true) {
                $record->isYoutube() => 'Modifier la vidéo YouTube',
                $record->isExternalImage() => 'Modifier l’image externe',
                $record->isVideo() => 'Modifier la vidéo',
                default => 'Modifier l’image',
            })
            ->modalWidth(Width::Screen)
            ->modalSubmitActionLabel('Enregistrer')
            ->extraModalFooterActions(fn (): array => [$this->deleteFromPopupAction()])
            ->fillForm(fn (LibraryMedia $record): array => [
                'name' => $record->name,
                'caption' => $record->getCustomProperty('caption'),
                'alt' => $record->getCustomProperty('alt'),
                'taken_at' => $record->taken_at,
                'latitude' => $record->latitude,
                'longitude' => $record->longitude,
                'tags' => $record->tagNames($this->orchestration->libraryTagType()),
                'trim_start' => $record->trim()['start'],
                'trim_end' => $record->trim()['end'],
                'copyright' => $record->copyright(),
                'youtube_url' => $record->youtubeUrl(),
            ])
            ->schema([
                Grid::make(['default' => 1, 'lg' => 5])->gap()->schema([
                    SchemaGroup::make([
                        ViewComponent::make('filament-orchestrator::library.media-info')
                            ->viewData(fn (LibraryMedia $record): array => ['media' => $record]),
                        TextInput::make('name')->label('Nom')->required()->maxLength(255),
                        Textarea::make('caption')->label('Légende')->rows(2)->maxLength(500)
                            ->helperText('Affichée sous la photo ou la vidéo dans le carnet.'),
                        TextInput::make('alt')->label('Texte alternatif')->maxLength(255)
                            ->helperText('Décrit son contenu à qui ne peut pas le voir.'),
                        DateTimePicker::make('taken_at')
                            ->label('Date de prise de vue')
                            // L'heure de l'appareil, telle quelle : le fuseau de l'utilisateur (que l'application applique aux
                            // sélecteurs de date) ne la décalerait que d'un cran de plus (voir LibraryMedia::takenAt()).
                            ->timezone('UTC')
                            ->seconds(false)
                            ->visible(fn (LibraryMedia $record): bool => ! $record->isYoutube())
                            ->helperText('Pour dater un fichier sans date de prise de vue, ou corriger celle de l’appareil.'),
                        Grid::make(2)
                            ->visible(fn (LibraryMedia $record): bool => ! $record->isYoutube())
                            ->schema([
                                TextInput::make('latitude')->label('Latitude')->numeric()->minValue(-90)->maxValue(90)
                                    ->requiredWith('longitude'),
                                TextInput::make('longitude')->label('Longitude')->numeric()->minValue(-180)->maxValue(180)
                                    ->requiredWith('latitude'),
                            ]),
                        TextInput::make('copyright')->label('Copyright')->maxLength(255)
                            ->visible(fn (LibraryMedia $record): bool => $record->isExternalImage())
                            ->helperText('Le nom du photographe ou de la source, à créditer.'),
                        TextInput::make('youtube_url')->label('Lien de la vidéo')->maxLength(2048)
                            ->visible(fn (LibraryMedia $record): bool => $record->isYoutube())
                            ->helperText('Un autre lien remplace la vidéo : la vignette est retéléchargée.'),
                        $this->tagSelect('tags', 'Tags'),
                        // La coupe d'une vidéo : où le lecteur commence et s'arrête. Le fichier reste entier ; les boutons
                        // du lecteur (library/media-preview) reportent ici l'endroit où l'on est arrivé.
                        Grid::make(2)
                            ->visible(fn (LibraryMedia $record): bool => $record->isVideo())
                            ->schema([
                                TextInput::make('trim_start')->label('Début (s)')->numeric()->minValue(0)->step(0.1)
                                    ->extraInputAttributes(['data-trim' => 'trim_start']),
                                TextInput::make('trim_end')->label('Fin (s)')->numeric()->minValue(0)->step(0.1)
                                    // Après le début — quand il y en a un : une coupe peut n'avoir qu'une fin.
                                    ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                        if (is_numeric($value) && is_numeric($get('trim_start')) && (float) $value <= (float) $get('trim_start')) {
                                            $fail('La fin doit venir après le début.');
                                        }
                                    })
                                    ->extraInputAttributes(['data-trim' => 'trim_end']),
                            ]),
                    ])->columnSpan(['lg' => 1]),
                    ViewComponent::make('filament-orchestrator::library.media-preview')
                        ->viewData(fn (LibraryMedia $record): array => ['media' => $record])
                        ->columnSpan(['lg' => 4]),
                ]),
            ])
            ->action(function (LibraryMedia $record, array $data): void {
                // Un autre lien YouTube collé ici : au fond, une autre vidéo. On la ré-ingère (nouvelle vignette),
                // en gardant nom/légende/texte alternatif/tags de la fiche qu'elle remplace, puis l'ancienne fiche
                // disparaît. Le reste de l'action ne s'applique plus : elle s'arrête ici dans ce cas.
                if ($record->isYoutube()) {
                    $newVideoId = YoutubeUrl::id($data['youtube_url'] ?? null);

                    if ($newVideoId !== null && $newVideoId !== $record->youtubeId()) {
                        $fresh = app(LibraryIngestor::class)->ingestYoutube($this->orchestration, $newVideoId);

                        foreach (['caption', 'alt'] as $property) {
                            filled($record->getCustomProperty($property))
                                ? $fresh->setCustomProperty($property, $record->getCustomProperty($property))
                                : $fresh->forgetCustomProperty($property);
                        }

                        $fresh->forceFill([
                            'name' => filled($data['name'] ?? null) ? trim((string) $data['name']) : $record->name,
                        ])->save();
                        $fresh->syncTagsWithType($this->cleanTags($data['tags'] ?? []), $this->orchestration->libraryTagType());

                        $record->delete();

                        $this->dispatch(MediaUploadAction::UPDATED_EVENT, orchestrationId: $this->orchestrationId);

                        return;
                    }
                }

                if ($record->isExternalImage()) {
                    filled($data['copyright'] ?? null)
                        ? $record->setCustomProperty('copyright', trim((string) $data['copyright']))
                        : $record->forgetCustomProperty('copyright');
                }

                $submitted = filled($data['taken_at'] ?? null) ? CarbonImmutable::parse($data['taken_at']) : null;

                // Le sélecteur n'a pas de secondes : la date qu'on lui a donnée (« 11:09:33 ») revient « 11:09:00 ». La comparer à
                // la minute près — sinon enregistrer la fenêtre sans toucher à la date la tiendrait pour changée, la
                // tronquerait, et ferait d'une date « du chargement » une date « saisie à la main », donc fiable.
                $changed = $submitted?->format('Y-m-d H:i') !== $record->taken_at?->format('Y-m-d H:i');
                $takenAt = $changed ? $submitted : $record->taken_at;

                // Une date changée à la main n'est plus « celle du fichier » : elle devient fiable.
                if ($changed && $takenAt !== null) {
                    $record->setCustomProperty('date_source', MediaMetadata::SOURCE_MANUAL);
                }

                // Vide : la propriété disparaît, et le carnet retombe sur le nom (texte alternatif) ou n'écrit rien (légende).
                foreach (['caption', 'alt'] as $property) {
                    filled($data[$property] ?? null)
                        ? $record->setCustomProperty($property, trim((string) $data[$property]))
                        : $record->forgetCustomProperty($property);
                }

                if ($record->isVideo()) {
                    foreach (['trim_start', 'trim_end'] as $property) {
                        is_numeric($data[$property] ?? null) && (float) $data[$property] > 0
                            ? $record->setCustomProperty($property, (float) $data[$property])
                            : $record->forgetCustomProperty($property);
                    }
                }

                $record->forceFill([
                    'name' => filled($data['name'] ?? null) ? trim((string) $data['name']) : $record->name,
                    'taken_at' => $takenAt,
                    'latitude' => $data['latitude'] ?? null,
                    'longitude' => $data['longitude'] ?? null,
                ])->save();

                $record->syncTagsWithType($this->cleanTags($data['tags'] ?? []), $this->orchestration->libraryTagType());

                $this->dispatch(MediaUploadAction::UPDATED_EVENT, orchestrationId: $this->orchestrationId);
            });
    }

    /**
     * Le menu de la sélection : toujours visible dans la barre d'outils.
     *
     * Ce sont des actions ordinaires qui lisent la sélection
     * (`accessSelectedRecords()`), et non des BulkAction, que Filament ne
     * montre qu'une fois une case cochée : le menu reste à sa place, et
     * chaque action prévient si rien n'est coché.
     */
    private function selectionActions(): ActionGroup
    {
        return ActionGroup::make([
            ...$this->removeFromFocusActions(),
            $this->tagAction(),
            $this->untagAction(),
            ...$this->libraryActionButtons(shortcut: false),
            $this->deleteAction(),
        ])
            ->label('Sélection')
            ->icon('heroicon-m-ellipsis-vertical')
            ->button()
            ->size(Size::Small)
            ->color('gray')
            ->labeledFrom('sm')
            ->dropdownPlacement('bottom-start');
    }

    /** Une action qui porte sur les images cochées, et refuse de s'ouvrir si aucune ne l'est. */
    private function selectionAction(string $name): Action
    {
        return Action::make($name)
            ->accessSelectedRecords()
            ->deselectRecordsAfterCompletion()
            ->mountUsing(function (Action $action, ?Schema $schema, Collection $selectedRecords): void {
                if ($selectedRecords->isEmpty()) {
                    Notification::make()->warning()->title('Cochez d’abord des images')->send();

                    $action->halt();
                }

                $schema?->fill();
            });
    }

    /**
     * Les actions ajoutées par l'application (`library.actions`), proposées dans
     * cette bibliothèque.
     *
     * @return array<int, LibraryAction>
     */
    private function libraryActions(): array
    {
        return collect((array) config('filament-orchestrator.library.actions', []))
            ->map(fn (string $class): mixed => app($class))
            ->filter(fn (mixed $action): bool => $action instanceof LibraryAction && $action->appliesTo($this->libraryContext))
            ->values()
            ->all();
    }

    /**
     * Les gestes qu'on fait sans cesse, en boutons toujours visibles de la barre d'outils : ajouter les images cochées à
     * ce au service de quoi la bibliothèque est ouverte, puis les actions de l'application qui se déclarent
     * `shortcut()`. Comme celles de la sélection, elles préviennent si rien n'est coché.
     *
     * @return array<int, Action>
     */
    private function shortcutActions(): array
    {
        return [
            ...$this->addToFocusActions(),
            ...array_map(
                fn (Action $action): Action => $action->button()->outlined()->color('gray')->size(Size::Small),
                $this->libraryActionButtons(shortcut: true),
            ),
        ];
    }

    /**
     * Les actions de l'application, comme celles de la sélection : elles portent sur les images cochées.
     *
     * @param  bool  $shortcut  Celles qui sont des boutons de la barre d'outils, ou celles du menu de la sélection.
     * @return array<int, Action>
     */
    private function libraryActionButtons(bool $shortcut): array
    {
        return array_map(
            fn (LibraryAction $libraryAction): Action => $this->selectionAction('library'.Str::studly($libraryAction->getName()))
                // Un raccourci de la barre d'outils manque de place : libellé court, le complet en infobulle.
                ->label($shortcut ? $libraryAction->getShortLabel() : $libraryAction->getLabel())
                ->tooltip($shortcut ? $libraryAction->getLabel() : null)
                ->icon($libraryAction->getIcon())
                ->action(function (Collection $selectedRecords) use ($libraryAction): void {
                    if ($libraryAction->isSingle() && $selectedRecords->count() !== 1) {
                        Notification::make()->warning()->title('Cochez une seule image')->send();

                        return;
                    }

                    if ($selectedRecords->contains(fn (LibraryMedia $media): bool => ! $libraryAction->accepts($media))) {
                        Notification::make()->warning()->title($libraryAction->getRefusal())->send();

                        return;
                    }

                    $title = $libraryAction->handle($selectedRecords->values(), $this->libraryContext);

                    Notification::make()->success()->title($title ?? $libraryAction->getLabel())->send();
                    $this->dispatch(MediaUploadAction::UPDATED_EVENT, orchestrationId: $this->orchestrationId);
                }),
            array_values(array_filter(
                $this->libraryActions(),
                fn (LibraryAction $action): bool => $action->isShortcut() === $shortcut,
            )),
        );
    }

    /**
     * Les marques que porte une image : l'icône et le libellé de chaque action de
     * l'application qui la concerne.
     *
     * @return array<int, array{icon: string, label: string}>
     */
    private function marksOf(LibraryMedia $media): array
    {
        return collect($this->libraryActions())
            ->filter(fn (LibraryAction $action): bool => $action->marks($media, $this->libraryContext))
            ->map(fn (LibraryAction $action): array => ['icon' => $action->getIcon(), 'label' => $action->getLabel()])
            ->values()
            ->all();
    }

    /**
     * Le bouton toujours visible qui ajoute les images cochées à ce au service de quoi la bibliothèque est ouverte
     * (une période). Elles s'y rangent à la suite des autres, comme au glisser-déposer. Rien sans « service ».
     *
     * @return array<int, Action>
     */
    private function addToFocusActions(): array
    {
        if ($this->focusTags === []) {
            return [];
        }

        return [
            // « Ajouter » tout court : le bandeau du haut dit déjà à quoi, et la place manque dans un volet étroit.
            $this->selectionAction('addToFocus')
                ->label('Ajouter')
                ->icon('heroicon-m-plus-circle')
                ->tooltip('Ajouter les images cochées à : '.$this->focusLabel.' (elles se rangent à la suite des autres)')
                ->button()
                ->color('primary')
                ->size(Size::Small)
                ->extraAttributes(['data-library-shortcut' => 'add-to-focus'], merge: true)
                ->action(function (Collection $selectedRecords): void {
                    $images = new LibraryImages;
                    $added = $selectedRecords->filter(
                        fn (LibraryMedia $media): bool => $images->append($this->orchestration, $this->focusTags, $media),
                    )->count();

                    Notification::make()->success()
                        ->title($added === 0 ? 'Déjà présentes' : $added.' image(s) ajoutée(s)')
                        ->send();
                    $this->dispatch(MediaUploadAction::UPDATED_EVENT, orchestrationId: $this->orchestrationId);
                }),
        ];
    }

    /**
     * Retirer des images de ce au service de quoi la bibliothèque est ouverte : dans le menu de la sélection, un geste
     * moins fréquent.
     *
     * @return array<int, Action>
     */
    private function removeFromFocusActions(): array
    {
        if ($this->focusTags === []) {
            return [];
        }

        return [
            $this->selectionAction('removeFromFocus')
                ->label('Retirer de : '.$this->focusLabel)
                ->icon('heroicon-o-minus-circle')
                ->action(function (Collection $selectedRecords): void {
                    $selectedRecords->each(fn (LibraryMedia $media) => $media->detachTags($this->focusTags, $this->orchestration->libraryTagType()));

                    Notification::make()->success()->title($selectedRecords->count().' image(s) retirée(s)')->send();
                    $this->dispatch(MediaUploadAction::UPDATED_EVENT, orchestrationId: $this->orchestrationId);
                }),
        ];
    }

    /** @return array<int, Filter> */
    private function focusFilters(): array
    {
        if ($this->focusTags === []) {
            return [];
        }

        return [
            Filter::make('in_focus')
                ->label('Seulement : '.$this->focusLabel)
                ->toggle()
                ->query(fn (Builder $query): Builder => $query->withAnyTags($this->focusTags, $this->orchestration->libraryTagType())),
        ];
    }

    /** Le nom de ce au service de quoi la bibliothèque est ouverte (la journée), pour l'affichage. */
    #[Computed]
    public function focusLabel(): string
    {
        return implode(', ', array_map($this->tagLabel(...), $this->focusTags));
    }

    private function tagAction(): Action
    {
        return $this->selectionAction('tag')
            ->label('Ajouter des tags')
            ->icon('heroicon-o-tag')
            ->schema([$this->tagSelect('tags', 'Tags à ajouter')->required()])
            ->action(function (Collection $selectedRecords, array $data): void {
                $tags = $this->cleanTags($data['tags']);

                $selectedRecords->each(fn (LibraryMedia $media) => $media->attachTags($tags, $this->orchestration->libraryTagType()));

                Notification::make()->success()->title($selectedRecords->count().' image(s) étiquetée(s)')->send();
            });
    }

    private function untagAction(): Action
    {
        return $this->selectionAction('untag')
            ->label('Retirer des tags')
            ->icon('heroicon-o-x-circle')
            ->schema([$this->tagSelect('tags', 'Tags à retirer')->required()])
            ->action(function (Collection $selectedRecords, array $data): void {
                $tags = $this->cleanTags($data['tags']);

                $selectedRecords->each(fn (LibraryMedia $media) => $media->detachTags($tags, $this->orchestration->libraryTagType()));

                Notification::make()->success()->title($selectedRecords->count().' image(s) mise(s) à jour')->send();
            });
    }

    private function deleteAction(): Action
    {
        return $this->selectionAction('delete')
            ->label('Supprimer les images')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Supprimer les images cochées ?')
            ->modalDescription('Elles disparaissent de la bibliothèque et de toutes les journées où elles s’affichent. Cette action est définitive.')
            ->action(function (Collection $selectedRecords): void {
                $selectedRecords->each(fn (LibraryMedia $media) => $media->delete());

                Notification::make()->success()->title($selectedRecords->count().' image(s) supprimée(s)')->send();
                $this->dispatch(MediaUploadAction::UPDATED_EVENT, orchestrationId: $this->orchestrationId);
            });
    }

    /**
     * Sélecteur de tags : les valeurs sont les noms techniques, les libellés
     * ceux des labelers. Un nouveau tag se crée à la volée.
     */
    private function tagSelect(string $name, string $label): Select
    {
        return Select::make($name)
            ->label($label)
            ->multiple()
            ->searchable()
            ->options(fn (): array => $this->tagOptions)
            ->getOptionLabelsUsing(fn (array $values): array => collect($values)
                ->mapWithKeys(fn (string $tag): array => [$tag => $this->tagLabel($tag)])
                ->all())
            ->createOptionForm([TextInput::make('name')->label('Nouveau tag')->required()->maxLength(100)])
            ->createOptionUsing(fn (array $data): string => trim($data['name']));
    }

    /** @return array<int, string> */
    private function cleanTags(array $tags): array
    {
        return array_values(array_unique(array_filter(array_map('trim', $tags), 'strlen')));
    }

    /** Nombre de caractères d'un libellé de tag sur la carte ; le tooltip donne le libellé entier. */
    private const TAG_BADGE_LENGTH = 18;

    /** La taille en cours, ramenée à une valeur connue si la session en gardait une périmée. */
    private function currentSize(): string
    {
        return array_key_exists($this->size, self::SIZES) ? $this->size : 'm';
    }

    /** Les boutons S / M / L, en groupe, dans la barre d'outils. */
    private function sizeActions(): ActionGroup
    {
        return ActionGroup::make(array_map(
            fn (string $key, array $size): Action => Action::make('size'.strtoupper($key))
                ->label($size['label'])
                ->tooltip($size['hint'])
                ->size(Size::Small)
                ->color(fn (): string => $this->currentSize() === $key ? 'primary' : 'gray')
                ->action(function () use ($key): void {
                    $this->size = $key;
                }),
            array_keys(self::SIZES),
            self::SIZES,
        ))->buttonGroup();
    }

    /**
     * Fige (ou libère) l'affichage des images entières. Libre, l'image est
     * recadrée et ne se montre entière qu'au survol de sa carte ; figé, elle l'est
     * partout, tout le temps.
     */
    private function fitAction(): Action
    {
        return Action::make('toggleFit')
            ->iconButton()
            ->size(Size::Small)
            ->icon(fn (): string => $this->fit ? 'heroicon-m-arrows-pointing-in' : 'heroicon-m-arrows-pointing-out')
            ->color(fn (): string => $this->fit ? 'primary' : 'gray')
            ->tooltip(fn (): string => $this->fit
                ? 'Images entières en permanence — cliquer pour recadrer'
                : 'Images recadrées, entières au survol — cliquer pour les garder entières')
            ->action(function (): void {
                $this->fit = ! $this->fit;
            });
    }

    /** @return array<int, string> Les libellés de tous les tags de l'image. */
    private function tagLabels(LibraryMedia $media): array
    {
        return array_map($this->tagLabel(...), $media->tagNames($this->orchestration->libraryTagType()));
    }

    /**
     * Ce que la carte affiche de ses tags : les premiers, tronqués, puis un
     * « +N » pour les autres.
     *
     * @return array{badges: array<int, string>, overflow: string|null}
     */
    private function visibleTags(LibraryMedia $media): array
    {
        $labels = $this->tagLabels($media);
        $badges = array_map(
            fn (string $label): string => Str::limit($label, self::TAG_BADGE_LENGTH),
            array_slice($labels, 0, self::SIZES[$this->currentSize()]['tags']),
        );
        $hidden = count($labels) - count($badges);
        $overflow = $hidden > 0 ? '+'.$hidden : null;

        return ['badges' => $overflow === null ? $badges : [...$badges, $overflow], 'overflow' => $overflow];
    }

    private function tagLabel(string $tag): string
    {
        return app(TagLabels::class)->label($tag, $this->orchestration);
    }
}
