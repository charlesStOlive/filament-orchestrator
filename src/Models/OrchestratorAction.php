<?php

namespace CharlesStOlive\FilamentOrchestrator\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrchestratorAction extends Model
{
    protected $fillable = [
        'trigger_id',
        'target_node_id',
        'name',
        'key',
        'action',
        'target_role',
        'target_key',
        'parameters',
        'sort_order',
        'on_error',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getTable(): string
    {
        return config('filament-orchestrator.tables.actions', parent::getTable());
    }

    public function trigger(): BelongsTo
    {
        return $this->belongsTo(OrchestratorTrigger::class, 'trigger_id');
    }

    public function targetNode(): BelongsTo
    {
        return $this->belongsTo(OrchestratorNode::class, 'target_node_id');
    }
}
