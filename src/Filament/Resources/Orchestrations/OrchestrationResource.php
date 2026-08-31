<?php

namespace CharlesStOlive\FilamentOrchestrator\Filament\Resources\Orchestrations;

use CharlesStOlive\FilamentOrchestrator\Filament\Resources\Orchestrations\Pages\CreateOrchestration;
use CharlesStOlive\FilamentOrchestrator\Filament\Resources\Orchestrations\Pages\EditOrchestration;
use CharlesStOlive\FilamentOrchestrator\Filament\Resources\Orchestrations\Pages\ListOrchestrations;
use CharlesStOlive\FilamentOrchestrator\Filament\Resources\Orchestrations\RelationManagers\NodesRelationManager;
use CharlesStOlive\FilamentOrchestrator\Filament\Resources\Orchestrations\RelationManagers\TriggersRelationManager;
use CharlesStOlive\FilamentOrchestrator\Models\Orchestration;
use CharlesStOlive\FilamentOrchestrator\Registry\SchemaRegistry;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class OrchestrationResource extends Resource
{
    protected static ?string $model = Orchestration::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-command-line';

    protected static ?string $navigationLabel = 'Orchestrations';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Orchestration')
                ->description('Le schéma définit les rôles, événements et actions autorisés. Les données ci-dessous configurent une instance.')
                ->columns(2)
                ->schema([
                    Select::make('schema')
                        ->label('Schéma')
                        ->options(fn (): array => app(SchemaRegistry::class)->options())
                        ->required()
                        ->searchable()
                        ->live(),
                    Toggle::make('is_active')->label('Active')->default(true),
                    TextInput::make('name')
                        ->label('Nom')
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (?string $state, callable $set) => $set('key', Str::slug($state ?? ''))),
                    TextInput::make('key')->label('Clé stable')->required()->unique(ignoreRecord: true),
                    TextInput::make('event_scope')
                        ->label('Scope événementiel')
                        ->helperText('Laisser vide pour utiliser orchestration-{id}.'),
                    Textarea::make('description')->label('Description')->columnSpanFull(),
                    KeyValue::make('config')->label('Configuration')->columnSpanFull(),
                    KeyValue::make('initial_state')->label('État initial')->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nom')->searchable()->sortable(),
                TextColumn::make('schema')->label('Schéma')->badge(),
                TextColumn::make('key')->label('Clé')->searchable(),
                TextColumn::make('nodes_count')->counts('nodes')->label('Éléments'),
                TextColumn::make('triggers_count')->counts('triggers')->label('Déclencheurs'),
                IconColumn::make('is_active')->label('Active')->boolean(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getRelations(): array
    {
        return [NodesRelationManager::class, TriggersRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrchestrations::route('/'),
            'create' => CreateOrchestration::route('/create'),
            'edit' => EditOrchestration::route('/{record}/edit'),
        ];
    }
}
