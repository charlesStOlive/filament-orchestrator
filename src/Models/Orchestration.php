<?php

namespace CharlesStOlive\FilamentOrchestrator\Models;

use CharlesStOlive\FilamentOrchestrator\Registry\SchemaRegistry;
use CharlesStOlive\FilamentOrchestrator\Schemas\OrchestratorSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Orchestration extends Model
{
    protected $fillable = [
        'schema',
        'name',
        'key',
        'description',
        'event_scope',
        'config',
        'initial_state',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'initial_state' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function getTable(): string
    {
        return config('filament-orchestrator.tables.orchestrations', parent::getTable());
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(OrchestratorNode::class)->orderBy('sort_order')->orderBy('id');
    }

    public function triggers(): HasMany
    {
        return $this->hasMany(OrchestratorTrigger::class)->orderBy('sort_order')->orderBy('id');
    }

    public function scope(): string
    {
        return $this->event_scope ?: 'orchestration-'.$this->getKey();
    }

    public function schemaDefinition(): OrchestratorSchema
    {
        return app(SchemaRegistry::class)->get($this->schema);
    }
}
