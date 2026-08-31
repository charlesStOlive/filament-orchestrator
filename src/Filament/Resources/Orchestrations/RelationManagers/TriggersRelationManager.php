<?php

namespace CharlesStOlive\FilamentOrchestrator\Filament\Resources\Orchestrations\RelationManagers;

use CharlesStOlive\FilamentOrchestrator\Schemas\Definitions\ParameterDefinition;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TriggersRelationManager extends RelationManager
{
    protected static string $relationship = 'triggers';

    protected static ?string $title = 'Déclencheurs et actions';

    public function form(Schema $schema): Schema
    {
        $definition = $this->getOwnerRecord()->schemaDefinition();

        return $schema->components([
            TextInput::make('name')->label('Nom')->required(),
            TextInput::make('key')->label('Clé stable')->required(),
            Select::make('event')
                ->label('Événement')
                ->options($definition->eventCollection()->mapWithKeys(fn ($event): array => [$event->name => $event->label])->all())
                ->live()
                ->required()
                ->searchable(),
            Select::make('source_node_id')
                ->label('Élément source précis')
                ->options(function (Get $get) use ($definition): array {
                    $sourceRole = $definition->event((string) $get('event'))?->sourceRole;

                    return $this->getOwnerRecord()->nodes()
                        ->when($sourceRole, fn ($query) => $query->where('role', $sourceRole))
                        ->orderBy('role')->orderBy('key')->get()
                        ->mapWithKeys(fn ($node): array => [$node->getKey() => "{$node->role} · {$node->key}"])
                        ->all();
                })
                ->searchable(),
            Select::make('source_role')
                ->label('Ou rôle source')
                ->options(function (Get $get) use ($definition): array {
                    $sourceRole = $definition->event((string) $get('event'))?->sourceRole;
                    $nodes = $definition->nodeCollection();

                    return ($sourceRole ? $nodes->only($sourceRole) : $nodes)
                        ->mapWithKeys(fn ($node): array => [$node->role => $node->label])
                        ->all();
                }),
            TextInput::make('source_key')->label('Ou clé source'),
            KeyValue::make('conditions')->label('Conditions')->columnSpanFull(),
            TextInput::make('sort_order')->label('Ordre')->numeric()->default(0),
            Toggle::make('is_active')->label('Actif')->default(true),
            Repeater::make('actions')
                ->relationship()
                ->orderColumn('sort_order')
                ->columnSpanFull()
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => $state['name'] ?? $state['action'] ?? null)
                ->schema([
                    TextInput::make('name')->label('Nom'),
                    TextInput::make('key')->label('Clé')->required()->distinct(),
                    Select::make('action')
                        ->label('Action')
                        ->options($definition->actionCollection()->mapWithKeys(fn ($action): array => [$action->name => $action->label])->all())
                        ->live()
                        ->afterStateUpdated(function (callable $set): void {
                            $set('target_node_id', null);
                            $set('target_role', null);
                            $set('target_key', null);
                            $set('parameters', []);
                        })
                        ->required()
                        ->searchable(),
                    Select::make('target_node_id')
                        ->label('Élément cible')
                        ->options(function (Get $get) use ($definition): array {
                            $targetRole = $definition->action((string) $get('action'))?->targetRole;

                            return $this->getOwnerRecord()->nodes()
                                ->when($targetRole, fn ($query) => $query->where('role', $targetRole))
                                ->orderBy('role')->orderBy('key')->get()
                                ->mapWithKeys(fn ($node): array => [$node->getKey() => "{$node->role} · {$node->key}"])
                                ->all();
                        })
                        ->searchable(),
                    Select::make('target_role')
                        ->label('Ou rôle cible')
                        ->options(function (Get $get) use ($definition): array {
                            $targetRole = $definition->action((string) $get('action'))?->targetRole;
                            $nodes = $definition->nodeCollection();

                            return ($targetRole ? $nodes->only($targetRole) : $nodes)
                                ->mapWithKeys(fn ($node): array => [$node->role => $node->label])
                                ->all();
                        }),
                    TextInput::make('target_key')->label('Ou clé cible'),
                    Group::make()
                        ->schema(fn (Get $get): array => collect($definition->action((string) $get('action'))?->parameters ?? [])
                            ->map(fn (ParameterDefinition $parameter) => $this->parameterField($parameter))
                            ->all())
                        ->columnSpanFull(),
                    Select::make('on_error')
                        ->label('En cas d’erreur')
                        ->options(['continue' => 'Continuer', 'stop' => 'Arrêter la séquence'])
                        ->default('continue'),
                    Toggle::make('is_active')->label('Active')->default(true),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->label('Nom'),
                TextColumn::make('event')->label('Événement')->badge(),
                TextColumn::make('sourceNode.key')->label('Source'),
                TextColumn::make('actions_count')->counts('actions')->label('Actions'),
                IconColumn::make('is_active')->label('Actif')->boolean(),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->defaultSort('sort_order');
    }

    private function parameterField(ParameterDefinition $parameter): mixed
    {
        $field = match ($parameter->type) {
            'number' => TextInput::make("parameters.{$parameter->key}")->numeric(),
            'boolean' => Toggle::make("parameters.{$parameter->key}"),
            'select' => Select::make("parameters.{$parameter->key}")->options($parameter->options),
            'list' => TagsInput::make("parameters.{$parameter->key}"),
            'object' => KeyValue::make("parameters.{$parameter->key}"),
            default => TextInput::make("parameters.{$parameter->key}"),
        };

        return $field
            ->label($parameter->label ?? $parameter->key)
            ->required($parameter->required)
            ->default($parameter->default)
            ->helperText($parameter->help);
    }
}
