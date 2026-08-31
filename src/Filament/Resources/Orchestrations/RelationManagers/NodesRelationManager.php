<?php

namespace CharlesStOlive\FilamentOrchestrator\Filament\Resources\Orchestrations\RelationManagers;

use CharlesStOlive\FilamentOrchestrator\Models\OrchestratorNode;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NodesRelationManager extends RelationManager
{
    protected static string $relationship = 'nodes';

    protected static ?string $title = 'Éléments embarqués';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('role')
                ->label('Rôle')
                ->options(fn (): array => $this->getOwnerRecord()->schemaDefinition()->nodeCollection()->mapWithKeys(
                    fn ($definition): array => [$definition->role => $definition->label],
                )->all())
                ->required()
                ->live()
                ->afterStateUpdated(function (?string $state, callable $set): void {
                    $definition = $state ? $this->getOwnerRecord()->schemaDefinition()->node($state) : null;
                    $set('orchestratable_type', $definition?->model);
                    $set('ownership', $definition?->defaultOwnership);
                    $set('orchestratable_id', null);
                }),
            TextInput::make('key')
                ->label('Clé dans cette orchestration')
                ->required(),
            Select::make('orchestratable_type')
                ->label('Classe')
                ->options(fn (): array => $this->getOwnerRecord()->schemaDefinition()->nodeCollection()->mapWithKeys(
                    fn ($definition): array => [$definition->model => class_basename($definition->model)],
                )->all())
                ->required()
                ->disabled()
                ->dehydrated(),
            Select::make('orchestratable_id')
                ->label('Élément')
                ->options(function (Get $get): array {
                    $model = $get('orchestratable_type');

                    if (! is_string($model) || ! class_exists($model)) {
                        return [];
                    }

                    $instance = new $model;
                    $ownership = $get('ownership');
                    $unavailableIds = OrchestratorNode::query()
                        ->where('orchestratable_type', $instance->getMorphClass())
                        ->when(
                            $ownership === OrchestratorNode::OwnershipLinked,
                            fn ($query) => $query->where('ownership', OrchestratorNode::OwnershipOwned),
                        )
                        ->pluck('orchestratable_id');
                    $currentId = $get('orchestratable_id');

                    return $model::query()
                        ->when($unavailableIds->isNotEmpty(), function ($query) use ($unavailableIds, $currentId): void {
                            $query->where(function ($query) use ($unavailableIds, $currentId): void {
                                $query->whereNotIn($query->getModel()->getQualifiedKeyName(), $unavailableIds);

                                if (filled($currentId)) {
                                    $query->orWhereKey($currentId);
                                }
                            });
                        })
                        ->orderBy($this->labelColumn($model))
                        ->pluck($this->labelColumn($model), $instance->getKeyName())
                        ->all();
                })
                ->searchable()
                ->preload()
                ->required(),
            Select::make('ownership')
                ->label('Mode')
                ->options(function (Get $get): array {
                    $definition = $this->getOwnerRecord()->schemaDefinition()->node((string) $get('role'));
                    $labels = [
                        OrchestratorNode::OwnershipOwned => 'Propre à cette orchestration',
                        OrchestratorNode::OwnershipLinked => 'Lié depuis la bibliothèque',
                    ];

                    return collect($definition?->ownerships ?? [])
                        ->mapWithKeys(fn (string $ownership): array => [$ownership => $labels[$ownership] ?? $ownership])
                        ->all();
                })
                ->live()
                ->required(),
            TextInput::make('sort_order')->label('Ordre')->numeric()->default(0),
            Toggle::make('is_active')->label('Actif')->default(true),
            KeyValue::make('config')->label('Configuration')->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('key')
            ->columns([
                TextColumn::make('role')->label('Rôle')->badge(),
                TextColumn::make('key')->label('Clé'),
                TextColumn::make('orchestratable_type')->label('Classe')->formatStateUsing(fn (string $state): string => class_basename($state)),
                TextColumn::make('orchestratable_id')->label('ID'),
                TextColumn::make('ownership')->label('Mode')->badge(),
                IconColumn::make('is_active')->label('Actif')->boolean(),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->defaultSort('sort_order');
    }

    private function labelColumn(string $model): string
    {
        $instance = new $model;

        foreach (['name', 'title', 'key', 'slug'] as $column) {
            if (\Schema::hasColumn($instance->getTable(), $column)) {
                return $column;
            }
        }

        return $instance->getKeyName();
    }
}
