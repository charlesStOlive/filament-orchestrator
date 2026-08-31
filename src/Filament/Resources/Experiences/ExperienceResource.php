<?php

namespace CharlesStOlive\FilamentOrchestrator\Filament\Resources\Experiences;

use CharlesStOlive\FilamentOrchestrator\Filament\Resources\Experiences\Pages\CreateExperience;
use CharlesStOlive\FilamentOrchestrator\Filament\Resources\Experiences\Pages\EditExperience;
use CharlesStOlive\FilamentOrchestrator\Filament\Resources\Experiences\Pages\ListExperiences;
use CharlesStOlive\FilamentOrchestrator\Models\Experience;
use CharlesStOlive\FilamentOrchestrator\Support\ActionType;
use CharlesStOlive\FilamentOrchestrator\Support\TriggerType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ExperienceResource extends Resource
{
    protected static ?string $model = Experience::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bolt';

    protected static ?string $navigationLabel = 'Expériences';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Expérience')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Nom')
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (?string $state, callable $set) => $set('key', Str::slug($state ?? ''))),
                    TextInput::make('key')->label('Clé stable')->required()->unique(ignoreRecord: true),
                    static::mapSelect(),
                    TextInput::make('event_scope')
                        ->label('Scope événementiel')
                        ->helperText('Laisser vide pour utiliser experience-{id}.'),
                    Textarea::make('description')->columnSpanFull(),
                    Toggle::make('is_active')->label('Active')->default(true),
                    KeyValue::make('options')->columnSpanFull(),
                ]),
            Section::make('Contenus')
                ->description('Textes et images que les interactions pourront ouvrir.')
                ->schema([
                    Repeater::make('contents')
                        ->relationship()
                        ->columns(2)
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => $state['name'] ?? $state['key'] ?? null)
                        ->schema([
                            TextInput::make('name')->label('Nom')->required(),
                            TextInput::make('key')->label('Clé')->required()->distinct(),
                            TextInput::make('title')->label('Titre')->columnSpanFull(),
                            Textarea::make('body')->label('Texte')->rows(6)->columnSpanFull(),
                            TagsInput::make('images')
                                ->label('Images')
                                ->helperText('Une URL ou un chemin public par image.')
                                ->columnSpanFull(),
                            Toggle::make('is_active')->label('Actif')->default(true),
                            KeyValue::make('options')->columnSpanFull(),
                        ]),
                ]),
            Section::make('Interactions')
                ->description('Un déclencheur et une source produisent une suite d’actions ordonnées.')
                ->schema([
                    Repeater::make('interactions')
                        ->relationship()
                        ->orderColumn('sort_order')
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => $state['name'] ?? $state['key'] ?? null)
                        ->schema([
                            TextInput::make('name')->label('Nom')->required(),
                            TextInput::make('key')->label('Clé')->required()->distinct(),
                            Select::make('source_type')
                                ->label('Source')
                                ->options(['map.point' => 'Hotpoint de carte'])
                                ->default('map.point')
                                ->required(),
                            TextInput::make('source_key')
                                ->label('Identifiant du hotpoint')
                                ->helperText('Identifiant public reçu dans l’événement de la carte.'),
                            Select::make('trigger')
                                ->label('Déclencheur')
                                ->options(TriggerType::options())
                                ->default(TriggerType::Click)
                                ->live()
                                ->required(),
                            TextInput::make('trigger_event')
                                ->label('Événement')
                                ->required(fn (Get $get): bool => $get('trigger') === TriggerType::Event),
                            KeyValue::make('conditions')->columnSpanFull(),
                            Toggle::make('is_active')->label('Active')->default(true),
                            Repeater::make('actions')
                                ->relationship()
                                ->orderColumn('sort_order')
                                ->columns(2)
                                ->columnSpanFull()
                                ->collapsible()
                                ->itemLabel(fn (array $state): ?string => $state['name'] ?? $state['type'] ?? null)
                                ->schema([
                                    TextInput::make('name')->label('Nom'),
                                    TextInput::make('key')->label('Clé')->required()->distinct(),
                                    Select::make('type')
                                        ->label('Action')
                                        ->options(ActionType::options())
                                        ->required(),
                                    TextInput::make('target')
                                        ->label('Cible')
                                        ->helperText('Clé de contenu, de layer, nom d’événement ou URL.'),
                                    KeyValue::make('payload')->columnSpanFull(),
                                    KeyValue::make('options')->columnSpanFull(),
                                    Toggle::make('is_active')->label('Active')->default(true),
                                ]),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nom')->searchable()->sortable(),
                TextColumn::make('key')->label('Clé')->searchable(),
                TextColumn::make('map_id')->label('Carte'),
                TextColumn::make('interactions_count')->counts('interactions')->label('Interactions'),
                IconColumn::make('is_active')->label('Active')->boolean(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExperiences::route('/'),
            'create' => CreateExperience::route('/create'),
            'edit' => EditExperience::route('/{record}/edit'),
        ];
    }

    protected static function mapSelect(): Select
    {
        $mapModel = 'CharlesStOlive\\FilamentMap\\Models\\Map';

        return Select::make('map_id')
            ->label('Carte')
            ->options(fn (): array => class_exists($mapModel)
                ? $mapModel::query()->orderBy('name')->pluck('name', 'id')->all()
                : [])
            ->searchable()
            ->preload();
    }
}
