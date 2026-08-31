<?php

namespace CharlesStOlive\FilamentOrchestrator\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrchestratorTrigger extends Model
{
    protected $fillable = [
        'orchestration_id',
        'source_node_id',
        'name',
        'key',
        'event',
        'source_role',
        'source_key',
        'conditions',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getTable(): string
    {
        return config('filament-orchestrator.tables.triggers', parent::getTable());
    }

    public function orchestration(): BelongsTo
    {
        return $this->belongsTo(Orchestration::class);
    }

    public function sourceNode(): BelongsTo
    {
        return $this->belongsTo(OrchestratorNode::class, 'source_node_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(OrchestratorAction::class, 'trigger_id')->orderBy('sort_order')->orderBy('id');
    }
}
