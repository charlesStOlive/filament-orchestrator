<?php

namespace CharlesStOlive\FilamentOrchestrator\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Experience extends Model
{
    protected $fillable = [
        'name',
        'key',
        'description',
        'map_id',
        'event_scope',
        'options',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function getTable(): string
    {
        return config('filament-orchestrator.tables.experiences', parent::getTable());
    }

    public function contents(): HasMany
    {
        return $this->hasMany(ContentPanel::class)->orderBy('id');
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(Interaction::class)->orderBy('sort_order');
    }

    public function scope(): string
    {
        return $this->event_scope ?: 'experience-'.$this->getKey();
    }
}
