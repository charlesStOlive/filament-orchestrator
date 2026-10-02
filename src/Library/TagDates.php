<?php

namespace CharlesStOlive\FilamentOrchestrator\Library;

use CharlesStOlive\FilamentOrchestrator\Library\Contracts\LibraryTagDates;
use CharlesStOlive\FilamentOrchestrator\Models\Orchestration;

/** Résout les dates que couvrent des tags, à partir des LibraryTagDates configurés. */
final class TagDates
{
    /**
     * Les dates que couvrent ces tags, de la plus ancienne à la plus récente ; null quand aucun n'en a.
     *
     * @param  array<int, string>  $tags
     * @return array{from: string, until: string}|null
     */
    public function range(array $tags, Orchestration $orchestration): ?array
    {
        $ranges = array_values(array_filter(array_map(fn (string $tag): ?array => $this->dates($tag, $orchestration), $tags)));

        if ($ranges === []) {
            return null;
        }

        return [
            'from' => min(array_column($ranges, 'from')),
            'until' => max(array_column($ranges, 'until')),
        ];
    }

    /** @return array{from: string, until: string}|null */
    private function dates(string $tag, Orchestration $orchestration): ?array
    {
        foreach ((array) config('filament-orchestrator.library.tag_dates', []) as $class) {
            $provider = app($class);

            if (! $provider instanceof LibraryTagDates) {
                continue;
            }

            $dates = $provider->dates($tag, $orchestration);

            if (is_string($dates['from'] ?? null) && is_string($dates['until'] ?? null)) {
                return ['from' => $dates['from'], 'until' => max($dates['from'], $dates['until'])];
            }
        }

        return null;
    }
}
