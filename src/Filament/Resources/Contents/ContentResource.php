<?php

namespace CharlesStOlive\FilamentOrchestrator\Filament\Resources\Contents;

use CharlesStOlive\FilamentOrchestrator\Filament\Concerns\BelongsToConfiguredOrchestratorCluster;
use CharlesStOlive\FilamentOrchestrator\Filament\Resources\Contents\Pages\CreateContent;
use CharlesStOlive\FilamentOrchestrator\Filament\Resources\Contents\Pages\EditContent;
use CharlesStOlive\FilamentOrchestrator\Filament\Resources\Contents\Pages\ListContents;
use CharlesStOlive\FilamentOrchestrator\Library\RichEditor\PasteCleanupPlugin;
use CharlesStOlive\FilamentOrchestrator\Models\OrchestratorContent;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
use Illuminate\Support\Str;

class ContentResource extends Resource implements HasKnowledgeBase
{
    use BelongsToConfiguredOrchestratorCluster;

    protected static ?string $model = OrchestratorContent::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationLabel = 'Contenus narratifs';

    public static function getDocumentation(): array|string
    {
        return ['orchestrator.contenus', 'orchestrator.elements'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Contenu')->columns(2)->schema([
                TextInput::make('name')
                    ->label('Nom interne')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn(?string $state, callable $set) => $set('key', Str::slug($state ?? ''))),
                TextInput::make('key')->label('Clé de bibliothèque')->unique(ignoreRecord: true),
                TextInput::make('title')->label('Titre affiché')->columnSpanFull(),
                RichEditor::make('body')
                    ->label('Texte')
                    ->columnSpanFull()
                    ->fileAttachments(false)
                    ->plugins([PasteCleanupPlugin::make()])
                    ->toolbarButtons([
                        ['bold', 'italic', 'underline', 'strike', 'link'],
                        ['h2', 'h3'],
                        ['bulletList', 'orderedList', 'blockquote'],
                        ['clearFormatting', 'undo', 'redo'],
                    ]),
                SpatieMediaLibraryFileUpload::make('images')
                    ->label('Images')
                    ->collection('orchestrator_images')
                    ->image()
                    ->multiple()
                    ->reorderable()
                    ->columnSpanFull(),
                Repeater::make('buttons')
                    ->label('Boutons')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('key')->label('Clé stable')->required()->distinct(),
                        TextInput::make('label')->label('Libellé')->required(),
                    ]),
                Toggle::make('is_active')->label('Actif')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nom')->searchable()->sortable(),
                TextColumn::make('key')->label('Clé')->searchable(),
                TextColumn::make('orchestrator_nodes_count')->counts('orchestratorNodes')->label('Utilisations'),
                IconColumn::make('is_active')->label('Actif')->boolean(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContents::route('/'),
            'create' => CreateContent::route('/create'),
            'edit' => EditContent::route('/{record}/edit'),
        ];
    }
}
