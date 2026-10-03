<?php

namespace CharlesStOlive\FilamentOrchestrator\Library\Contracts;

use CharlesStOlive\FilamentOrchestrator\Models\LibraryMedia;

/**
 * Garde un fichier de la bibliothèque qu'on ne doit pas supprimer : une image que montre une version publiée, par
 * exemple. La bibliothèque dit alors pourquoi au lieu de le supprimer, et le modèle refuse la suppression, d'où qu'elle
 * vienne (voir LibraryMedia et DeletionGuards).
 *
 * Les gardes sont listées dans `filament-orchestrator.library.deletion_guards` ; la première qui retient le fichier
 * l'emporte.
 */
interface LibraryDeletionGuard
{
    /** @return string|null Pourquoi le fichier doit rester, en une phrase ; null s'il peut partir. */
    public function reason(LibraryMedia $media): ?string;
}
