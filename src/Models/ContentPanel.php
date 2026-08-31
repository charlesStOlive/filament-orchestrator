<?php

namespace CharlesStOlive\FilamentOrchestrator\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentPanel extends Model
{
    protected $fillable = [
        'experience_id',
        'name',
        'key',
        'title',
        'body',
        'images',
        'options',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'images' => 'array',
            'options' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function getTable(): string
    {
        return config('filament-orchestrator.tables.contents', parent::getTable());
    }

    public function experience(): BelongsTo
    {
        return $this->belongsTo(Experience::class);
    }
}
