<?php

namespace CharlesStOlive\FilamentOrchestrator\Library;

use CharlesStOlive\FilamentOrchestrator\Library\Contracts\LibraryTagLabeler;
use CharlesStOlive\FilamentOrchestrator\Models\Orchestration;

/** Résout le libellé affiché d'un tag, à partir des labelers configurés. */
final class TagLabels
{
    public function label(string $tag, Orchestration $orchestration): string
    {
        foreach ((array) config('filament-orchestrator.library.labelers', []) as $class) {
            $labeler = app($class);

            if (! $labeler instanceof LibraryTagLabeler) {
                continue;
            }

            $label = $labeler->label($tag, $orchestration);

            if (filled($label)) {
                return $label;
            }
        }

        return $tag;
    }
}
