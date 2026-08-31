<?php

namespace CharlesStOlive\FilamentOrchestrator\Filament\Resources\Orchestrations\RelationManagers;

use CharlesStOlive\FilamentOrchestrator\Filament\Resources\Orchestrations\OrchestrationResource;
use CharlesStOlive\FilamentOrchestrator\Models\OrchestratorNode;
use Filament\Actions\Action;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Crypt;

class NodesRelationManager extends RelationManager
{
    protected static string $relationship = 'nodes';

    protected static ?string $title = 'Éléments embarqués';

    protected static ?string $nodeRole = null;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('role')
                ->label('Rôle')
                ->options(fn (): array => $this->getOwnerRecord()->schemaDefinition()->nodeCollection()->mapWithKeys(
                    fn ($definition): array => [$definition->role => $definition->label],
                )->all())
                ->default(static::$nodeRole)
                ->required()
                ->live()
                ->disabled(filled(static::$nodeRole))
                ->dehydrated()
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
                ->default(fn (): ?string => $this->nodeDefinition()?->model)
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
                ->default(fn (): ?string => $this->nodeDefinition()?->defaultOwnership)
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
            ->modifyQueryUsing(function (Builder $query): void {
                if (filled(static::$nodeRole)) {
                    $query->where('role', static::$nodeRole);
                }
            })
            ->columns([
                TextColumn::make('role')->label('Rôle')->badge()->visible(blank(static::$nodeRole)),
                TextColumn::make('key')->label('Clé'),
                TextColumn::make('orchestratable_label')
                    ->label('Élément')
                    ->state(fn (OrchestratorNode $record): string => $this->recordLabel($record)),
                TextColumn::make('orchestratable_type')
                    ->label('Classe')
                    ->formatStateUsing(fn (string $state): string => class_basename($state))
                    ->visible(blank(static::$nodeRole)),
                TextColumn::make('orchestratable_id')->label('ID')->visible(blank(static::$nodeRole)),
                TextColumn::make('ownership')->label('Mode')->badge(),
                IconColumn::make('is_active')->label('Actif')->boolean(),
            ])
            ->headerActions(array_filter([
                $this->nativeCreateAction(),
                CreateAction::make()
                    ->label($this->attachActionLabel())
                    ->fillForm(fn (): array => array_filter([
                        'role' => static::$nodeRole,
                        'orchestratable_type' => $this->nodeDefinition()?->model,
                        'ownership' => $this->nodeDefinition()?->defaultOwnership,
                        'is_active' => true,
                    ])),
            ]))
            ->recordActions([
                Action::make('deepEdit')
                    ->label('Ouvrir la ressource')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (OrchestratorNode $record): ?string => $this->deepEditUrl($record))
                    ->visible(fn (OrchestratorNode $record): bool => $this->canDeepEdit($record)),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('sort_order');
    }

    protected function nodeDefinition(): mixed
    {
        return static::$nodeRole
            ? $this->getOwnerRecord()->schemaDefinition()->node(static::$nodeRole)
            : null;
    }

    protected function nativeCreateAction(): ?Action
    {
        if (! static::$nodeRole) {
            return null;
        }

        $resource = config('filament-orchestrator.node_management.roles.'.static::$nodeRole.'.resource');

        if (! is_string($resource) || ! class_exists($resource) || ! $resource::hasPage('create') || ! $resource::canCreate()) {
            return null;
        }

        $orchestration = $this->getOwnerRecord();
        $returnUrl = OrchestrationResource::getUrl('edit', ['record' => $orchestration]);
        $context = Crypt::encryptString(json_encode([
            'orchestration_id' => $orchestration->getKey(),
            'role' => static::$nodeRole,
        ], JSON_THROW_ON_ERROR));

        return Action::make('createNativeResource')
            ->label($this->createActionLabel())
            ->icon('heroicon-o-plus')
            ->url($resource::getUrl('create', [
                'context' => $context,
                'return' => $returnUrl,
                'return_label' => $orchestration->name,
            ]));
    }

    protected function createActionLabel(): string
    {
        return 'Créer '.match (static::$nodeRole) {
            'map' => 'une carte',
            'point' => 'un hotpoint',
            'layer' => 'une couche',
            'content' => 'un contenu',
            default => 'un élément',
        };
    }

    protected function attachActionLabel(): string
    {
        if (! static::$nodeRole) {
            return 'Ajouter un élément';
        }

        return 'Rattacher '.match (static::$nodeRole) {
            'map' => 'une carte existante',
            'point' => 'un hotpoint existant',
            'layer' => 'une couche existante',
            'content' => 'un contenu existant',
            default => 'un élément existant',
        };
    }

    protected function canDeepEdit(OrchestratorNode $node): bool
    {
        $resource = $this->resourceFor($node);

        return is_string($resource)
            && class_exists($resource)
            && $node->orchestratable !== null
            && $resource::hasPage('edit')
            && $resource::canEdit($node->orchestratable);
    }

    protected function deepEditUrl(OrchestratorNode $node): ?string
    {
        if (! $this->canDeepEdit($node)) {
            return null;
        }

        $resource = $this->resourceFor($node);
        $orchestration = $this->getOwnerRecord();

        return $resource::getUrl('edit', [
            'record' => $node->orchestratable,
            'return' => OrchestrationResource::getUrl('edit', ['record' => $orchestration]),
            'return_label' => $orchestration->name,
        ]);
    }

    protected function resourceFor(OrchestratorNode $node): ?string
    {
        return config("filament-orchestrator.node_management.roles.{$node->role}.resource");
    }

    protected function recordLabel(OrchestratorNode $node): string
    {
        $record = $node->orchestratable;

        foreach (['name', 'title', 'key', 'slug'] as $attribute) {
            if (filled($value = $record?->getAttribute($attribute))) {
                return (string) $value;
            }
        }

        return '#'.$node->orchestratable_id;
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
