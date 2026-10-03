<?php

namespace CharlesStOlive\FilamentOrchestrator\Library;

use CharlesStOlive\FilamentOrchestrator\Models\LibraryMedia;
use RuntimeException;

/**
 * Un fichier de la bibliothèque qu'une garde retient (voir Contracts\LibraryDeletionGuard) : sa suppression est refusée.
 * La bibliothèque ne la tente pas ; cette exception arrête tout autre chemin (la suppression d'une orchestration, qui
 * emporte ses fichiers, une commande…).
 */
final class MediaInUse extends RuntimeException
{
    public function __construct(public readonly LibraryMedia $media, public readonly string $reason)
    {
        parent::__construct(sprintf('« %s » ne peut pas être supprimé : %s', $media->name, $reason));
    }
}
