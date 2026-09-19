<?php

namespace CharlesStOlive\FilamentOrchestrator\Library\Livewire;

use Carbon\CarbonImmutable;
use CharlesStOlive\FilamentOrchestrator\Library\Filament\MediaUploadAction;
use CharlesStOlive\FilamentOrchestrator\Library\TagLabels;
use CharlesStOlive\FilamentOrchestrator\Models\LibraryMedia;
use CharlesStOlive\FilamentOrchestrator\Models\LibraryTag;
use CharlesStOlive\FilamentOrchestrator\Models\Orchestration;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
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
     * La taille des vignettes : S, M ou L. Gardée dans la session de
     * l'utilisateur, elle survit à la fermeture de la bibliothèque.
     */
    #[Session(key: 'orchestrator-library-size')]
    #[Locked]
    public string $size = 'm';

    /**
     * Les trois tailles. `grid` est le nombre de cartes par ligne selon la
     * largeur de l'écran, et `tags` le nombre de tags nommés sur la carte avant
     * le « +N ».
     *
     * @var array<string, array{label: string, hint: string, grid: array<string, int>, tags: int}>
     */
    private const SIZES = [
        's' => ['label' => 'S', 'hint' => 'Petites vignettes, en icônes', 'grid' => ['default' => 4, 'md' => 8, 'xl' => 10], 'tags' => 0],
        'm' => ['label' => 'M', 'hint' => 'Vignettes normales', 'grid' => ['default' => 2, 'md' => 4, 'xl' => 5], 'tags' => 1],
        'l' => ['label' => 'L', 'hint' => 'Grandes vignettes', 'grid' => ['default' => 1, 'md' => 2, 'xl' => 3], 'tags' => 2],
    ];

    /** @param array<int, string> $focusTags */
    public function mount(int $orchestrationId, array $focusTags = []): void
    {
        $this->orchestrationId = $orchestrationId;
        $this->focusTags = array_values(array_filter($focusTags, 'is_string'));

        // Échoue tôt (404) plutôt qu'à l'affichage de la table.
        $this->orchestration();
    }

    public function boot(): void
    {
        abort_unless(auth()->check(), 403);
    }

    /** Un envoi terminé ailleurs : le simple fait de recevoir l'événement rafraîchit la grille. */
    #[On(MediaUploadAction::UPDATED_EVENT)]
    public function refreshLibrary(): void {}

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
                            'tags' => $this->visibleTags($record),
                            'tagLabels' => $this->tagLabels($record),
                        ]),
                ]),
            ])
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
                    ->label('Ajouter des images'),
                $this->selectionActions(),
                $this->sizeActions(),
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
            ->modalHeading('Supprimer cette image ?')
            ->modalDescription(function (LibraryMedia $record): string {
                $usedBy = $this->tagLabels($record);

                return 'Elle disparaît de la bibliothèque'
                    .($usedBy === [] ? '' : ' et de : '.implode(', ', $usedBy))
                    .'. Cette action est définitive.';
            })
            ->modalSubmitActionLabel('Supprimer')
            ->action(function (LibraryMedia $record): void {
                $record->delete();

                Notification::make()->success()->title('Image supprimée')->send();
                $this->dispatch(MediaUploadAction::UPDATED_EVENT, orchestrationId: $this->orchestrationId);
            });
    }

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
            ->modalHeading('Modifier l’image')
            ->modalWidth(Width::Large)
            ->fillForm(fn (LibraryMedia $record): array => [
                'taken_at' => $record->taken_at,
                'latitude' => $record->latitude,
                'longitude' => $record->longitude,
                'tags' => $record->tagNames($this->orchestration->libraryTagType()),
            ])
            ->schema([
                DateTimePicker::make('taken_at')
                    ->label('Date de prise de vue')
                    ->seconds(false)
                    ->helperText('Pour dater une photo sans EXIF, ou corriger celle de l’appareil.'),
                TextInput::make('latitude')->label('Latitude')->numeric()->minValue(-90)->maxValue(90)
                    ->requiredWith('longitude'),
                TextInput::make('longitude')->label('Longitude')->numeric()->minValue(-180)->maxValue(180)
                    ->requiredWith('latitude'),
                $this->tagSelect('tags', 'Tags'),
            ])
            ->action(function (LibraryMedia $record, array $data): void {
                $record->forceFill([
                    'taken_at' => $data['taken_at'] ?? null,
                    'latitude' => $data['latitude'] ?? null,
                    'longitude' => $data['longitude'] ?? null,
                ])->save();

                $record->syncTagsWithType($this->cleanTags($data['tags'] ?? []), $this->orchestration->libraryTagType());
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
            ...$this->focusActions(),
            $this->tagAction(),
            $this->untagAction(),
            $this->deleteAction(),
        ])
            ->label('Sélection')
            ->icon('heroicon-m-ellipsis-vertical')
            ->button()
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
     * Rattacher des images au service courant (à une journée) ou les en retirer.
     *
     * @return array<int, Action>
     */
    private function focusActions(): array
    {
        if ($this->focusTags === []) {
            return [];
        }

        $label = $this->focusLabel();

        return [
            $this->selectionAction('addToFocus')
                ->label('Ajouter à : '.$label)
                ->icon('heroicon-o-plus-circle')
                ->action(function (Collection $selectedRecords): void {
                    $selectedRecords->each(fn (LibraryMedia $media) => $media->attachTags($this->focusTags, $this->orchestration->libraryTagType()));

                    Notification::make()->success()->title($selectedRecords->count().' image(s) ajoutée(s)')->send();
                    $this->dispatch(MediaUploadAction::UPDATED_EVENT, orchestrationId: $this->orchestrationId);
                }),
            $this->selectionAction('removeFromFocus')
                ->label('Retirer de : '.$label)
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
                ->label('Seulement : '.$this->focusLabel())
                ->toggle()
                ->query(fn (Builder $query): Builder => $query->withAnyTags($this->focusTags, $this->orchestration->libraryTagType())),
        ];
    }

    private function focusLabel(): string
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
                ->color(fn (): string => $this->currentSize() === $key ? 'primary' : 'gray')
                ->action(function () use ($key): void {
                    $this->size = $key;
                }),
            array_keys(self::SIZES),
            self::SIZES,
        ))->buttonGroup();
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
