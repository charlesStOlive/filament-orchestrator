<?php

namespace CharlesStOlive\FilamentOrchestrator\Library\Contracts;

use CharlesStOlive\FilamentOrchestrator\Models\Orchestration;

/**
 * Donne les dates que couvre un tag : celles d'une journée, pour le tag « day:3f9c… ». Ouverte au service de ce tag,
 * la bibliothèque propose alors de se filtrer sur ces dates de prise de vue (voir MediaLibraryTable).
 *
 * Comme les labelers, ils sont listés dans `filament-orchestrator.library.tag_dates` ; le premier qui reconnaît le tag
 * l'emporte. Un tag qu'aucun ne reconnaît n'a pas de dates : pas de bouton, rien d'autre ne change.
 */
interface LibraryTagDates
{
    /** @return array{from: string, until: string}|null Les dates (Y-m-d), ou null si ce tag n'en a pas pour lui. */
    public function dates(string $tag, Orchestration $orchestration): ?array;
}
