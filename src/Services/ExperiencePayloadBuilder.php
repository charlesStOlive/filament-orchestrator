<?php

namespace CharlesStOlive\FilamentOrchestrator\Services;

use CharlesStOlive\FilamentOrchestrator\Models\Action;
use CharlesStOlive\FilamentOrchestrator\Models\ContentPanel;
use CharlesStOlive\FilamentOrchestrator\Models\Experience;
use CharlesStOlive\FilamentOrchestrator\Models\Interaction;

class ExperiencePayloadBuilder
{
    public function build(Experience $experience): array
    {
        $experience->loadMissing(['contents', 'interactions.actions']);

        return [
            'experience' => [
                'id' => $experience->getKey(),
                'key' => $experience->key,
                'mapId' => $experience->map_id,
                'scope' => $experience->scope(),
                'options' => $experience->options ?? [],
            ],
            'contents' => $experience->contents
                ->where('is_active', true)
                ->mapWithKeys(fn (ContentPanel $content): array => [
                    $content->key => [
                        'key' => $content->key,
                        'title' => $content->title ?: $content->name,
                        'body' => $content->body,
                        'images' => array_values($content->images ?? []),
                        'options' => $content->options ?? [],
                    ],
                ])
                ->all(),
            'interactions' => $experience->interactions
                ->where('is_active', true)
                ->values()
                ->map(fn (Interaction $interaction): array => [
                    'key' => $interaction->key,
                    'source' => [
                        'type' => $interaction->source_type,
                        'key' => $interaction->source_key,
                    ],
                    'trigger' => [
                        'type' => $interaction->trigger,
                        'event' => $interaction->trigger_event,
                    ],
                    'conditions' => $interaction->conditions ?? [],
                    'actions' => $interaction->actions
                        ->where('is_active', true)
                        ->values()
                        ->map(fn (Action $action): array => [
                            'key' => $action->key,
                            'type' => $action->type,
                            'target' => $action->target,
                            'payload' => $action->payload ?? [],
                            'options' => $action->options ?? [],
                        ])
                        ->all(),
                ])
                ->all(),
        ];
    }
}
