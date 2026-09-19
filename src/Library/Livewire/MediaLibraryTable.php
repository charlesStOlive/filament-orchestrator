<?php

namespace CharlesStOlive\FilamentOrchestrator\Library\Livewire;

use Carbon\CarbonImmutable;
use CharlesStOlive\FilamentOrchestrator\Library\TagLabels;
use CharlesStOlive\FilamentOrchestrator\Models\LibraryMedia;
use CharlesStOlive\FilamentOrchestrator\Models\LibraryTag;
use CharlesStOlive\FilamentOrchestrator\Models\Orchestration;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\TextSize;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Filament\Tables\TableComponent;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;

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

    public function mount(int $orchestrationId): void
    {
        $this->orchestrationId = $orchestrationId;

        // Échoue tôt (404) plutôt qu'à l'affichage de la table.
        $this->orchestration();
    }

    public function boot(): void
    {
        abort_unless(auth()->check(), 403);
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
                Stack::make([
                    ViewColumn::make('thumbnail')->view('filament-orchestrator::library.thumbnail'),
                    Split::make([
                        TextColumn::make('taken_at')
                            ->label('Date de prise de vue')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('Sans date')
                            ->size(TextSize::ExtraSmall)
                            ->color('gray')
                            ->sortable(),
                        IconColumn::make('gps')
                            ->label('Position GPS')
                            ->state(fn (LibraryMedia $record): bool => $record->hasGps())
                            ->boolean()
                            ->trueIcon('heroicon-o-map-pin')
                            ->falseIcon('heroicon-o-map-pin')
                            ->trueColor('success')
                            ->falseColor('gray')
                            ->grow(false),
                    ]),
                    TextColumn::make('library_tags')
                        ->label('Tags')
                        ->state(fn (LibraryMedia $record): array => array_map(
                            $this->tagLabel(...),
                            $record->tagNames($this->orchestration->libraryTagType()),
                        ))
                        ->badge()
                        ->placeholder('Sans tag'),
                ])->space(2),
            ])
            ->contentGrid(['default' => 2, 'md' => 3, 'xl' => 5])
            ->defaultSort('taken_at')
            ->paginated([24, 48, 96])
            ->defaultPaginationPageOption(48)
            ->filters($this->filters(), layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(4)
            ->groups([
                $this->dateGroup(),
                $this->zoneGroup('zone_fine', 'Zone (environ 1 km)', 2),
                $this->zoneGroup('zone_large', 'Zone (environ 10 km)', 1),
            ])
            ->recordActions([$this->editAction()])
            ->recordAction('edit')
            ->toolbarActions([
                BulkActionGroup::make([
                    $this->tagAction(),
                    $this->untagAction(),
                    DeleteBulkAction::make()->label('Supprimer les images'),
                ]),
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

    private function editAction(): Action
    {
        return Action::make('edit')
            ->label('Modifier')
            ->icon('heroicon-o-pencil-square')
            ->iconButton()
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

    private function tagAction(): BulkAction
    {
        return BulkAction::make('tag')
            ->label('Ajouter des tags')
            ->icon('heroicon-o-tag')
            ->schema([$this->tagSelect('tags', 'Tags à ajouter')->required()])
            ->action(function (Collection $records, array $data): void {
                $tags = $this->cleanTags($data['tags']);

                $records->each(fn (LibraryMedia $media) => $media->attachTags($tags, $this->orchestration->libraryTagType()));

                Notification::make()->success()->title($records->count().' image(s) étiquetée(s)')->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    private function untagAction(): BulkAction
    {
        return BulkAction::make('untag')
            ->label('Retirer des tags')
            ->icon('heroicon-o-x-circle')
            ->schema([$this->tagSelect('tags', 'Tags à retirer')->required()])
            ->action(function (Collection $records, array $data): void {
                $tags = $this->cleanTags($data['tags']);

                $records->each(fn (LibraryMedia $media) => $media->detachTags($tags, $this->orchestration->libraryTagType()));

                Notification::make()->success()->title($records->count().' image(s) mise(s) à jour')->send();
            })
            ->deselectRecordsAfterCompletion();
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

    private function tagLabel(string $tag): string
    {
        return app(TagLabels::class)->label($tag, $this->orchestration);
    }
}
