<?php

namespace CharlesStOlive\FilamentOrchestrator\Library;

use CharlesStOlive\FilamentOrchestrator\Library\Contracts\LibraryDeletionGuard;
use CharlesStOlive\FilamentOrchestrator\Models\LibraryMedia;

/** Interroge les gardes configurées (`filament-orchestrator.library.deletion_guards`) avant la suppression d'un fichier. */
final class DeletionGuards
{
    /** Pourquoi ce fichier doit rester, ou null s'il peut être supprimé. */
    public function reason(LibraryMedia $media): ?string
    {
        foreach ((array) config('filament-orchestrator.library.deletion_guards', []) as $class) {
            $guard = app($class);

            if (! $guard instanceof LibraryDeletionGuard) {
                continue;
            }

            $reason = $guard->reason($media);

            if (filled($reason)) {
                return $reason;
            }
        }

        return null;
    }
}
