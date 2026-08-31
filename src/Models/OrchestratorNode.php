<?php

namespace CharlesStOlive\FilamentOrchestrator\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class OrchestratorNode extends Model
{
    public const OwnershipLinked = 'linked';

    public const OwnershipOwned = 'owned';

    protected $fillable = [
        'orchestration_id',
        'role',
        'key',
        'orchestratable_type',
        'orchestratable_id',
        'ownership',
        'config',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getTable(): string
    {
        return config('filament-orchestrator.tables.nodes', parent::getTable());
    }

    public function orchestration(): BelongsTo
    {
        return $this->belongsTo(Orchestration::class);
    }

    public function orchestratable(): MorphTo
    {
        return $this->morphTo();
    }

    public function sourceTriggers(): HasMany
    {
        return $this->hasMany(OrchestratorTrigger::class, 'source_node_id');
    }

    public function targetActions(): HasMany
    {
        return $this->hasMany(OrchestratorAction::class, 'target_node_id');
    }

    public function isOwned(): bool
    {
        return $this->ownership === self::OwnershipOwned;
    }
}
