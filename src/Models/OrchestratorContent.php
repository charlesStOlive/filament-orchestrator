<?php

namespace CharlesStOlive\FilamentOrchestrator\Models;

use CharlesStOlive\FilamentOrchestrator\Concerns\InteractsWithOrchestrator;
use CharlesStOlive\FilamentOrchestrator\Contracts\Orchestratable;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class OrchestratorContent extends Model implements HasMedia, Orchestratable
{
    use InteractsWithMedia;
    use InteractsWithOrchestrator;

    protected $fillable = [
        'name',
        'key',
        'title',
        'body',
        'buttons',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'buttons' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function getTable(): string
    {
        return config('filament-orchestrator.tables.contents', parent::getTable());
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('orchestrator_images');
    }

    public function toOrchestratorPayload(): array
    {
        return [
            'name' => $this->name,
            'title' => $this->title ?: $this->name,
            'body' => $this->body,
            'images' => $this->getMedia('orchestrator_images')
                ->map(fn ($media): array => [
                    'url' => $media->getUrl(),
                    'name' => $media->name,
                    'alt' => $media->getCustomProperty('alt', $media->name),
                ])
                ->values()
                ->all(),
            'buttons' => array_values($this->buttons ?? []),
        ];
    }
}
