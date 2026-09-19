<?php

namespace CharlesStOlive\FilamentOrchestrator\Library\Contracts;

use CharlesStOlive\FilamentOrchestrator\Library\IngestContext;
use CharlesStOlive\FilamentOrchestrator\Models\LibraryMedia;
use CharlesStOlive\FilamentOrchestrator\Models\Orchestration;

/**
 * Pose des tags automatiques sur une image qui vient d'entrer dans la
 * bibliothèque. La date et la position de l'image sont déjà renseignées.
 *
 * Les taggers sont listés dans `filament-orchestrator.library.taggers` :
 * chacun connaît une règle métier (par exemple « la journée dont la date est
 * celle de la prise de vue »), la bibliothèque n'en connaît aucune.
 */
interface LibraryTagger
{
    /** @return array<int, string> */
    public function tags(LibraryMedia $media, Orchestration $orchestration, IngestContext $context): array;
}
