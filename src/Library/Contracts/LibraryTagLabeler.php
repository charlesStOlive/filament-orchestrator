<?php

namespace CharlesStOlive\FilamentOrchestrator\Library\Contracts;

use CharlesStOlive\FilamentOrchestrator\Models\Orchestration;

/**
 * Donne un libellé lisible à un tag technique. Le tag « day:3f9c… » d'une
 * journée s'affiche « J1 » ou « Arrivée à Lisbonne » : le tag reste stable,
 * son libellé suit la journée quand elle est renommée ou déplacée.
 *
 * Les labelers sont listés dans `filament-orchestrator.library.labelers` ;
 * le premier qui reconnaît le tag l'emporte, sinon le nom du tag est affiché.
 */
interface LibraryTagLabeler
{
    /** @return string|null Le libellé, ou null si ce labeler ne connaît pas ce tag. */
    public function label(string $tag, Orchestration $orchestration): ?string;
}
