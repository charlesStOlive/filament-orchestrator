<?php

namespace CharlesStOlive\FilamentOrchestrator\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Interaction extends Model
{
    protected $fillable = [
        'experience_id',
        'name',
        'key',
        'source_type',
        'source_key',
        'trigger',
        'trigger_event',
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
        return config('filament-orchestrator.tables.interactions', parent::getTable());
    }

    public function experience(): BelongsTo
    {
        return $this->belongsTo(Experience::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(Action::class)->orderBy('sort_order');
    }
}
