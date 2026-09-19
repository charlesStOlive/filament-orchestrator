<?php

namespace CharlesStOlive\FilamentOrchestrator\Library;

use CharlesStOlive\FilamentOrchestrator\Models\Orchestration;

/**
 * Dans quelle bibliothèque, et « au service » de quoi, une action s'exécute.
 *
 * Les `focusTags` sont ceux de la journée (ou de l'introduction) depuis laquelle
 * la bibliothèque a été ouverte ; ils sont vides pour la bibliothèque générale.
 *
 * Le contexte partage un seul LibraryImages : toutes les cartes qui demandent
 * si elles sont marquées lisent la même bibliothèque chargée une fois.
 */
final class LibraryContext
{
    private ?LibraryImages $images = null;

    /** @param array<int, string> $focusTags */
    public function __construct(
        public readonly Orchestration $orchestration,
        public readonly array $focusTags = [],
    ) {}

    /** Ouverte depuis une journée (ou une introduction), et non la bibliothèque générale. */
    public function hasFocus(): bool
    {
        return $this->focusTags !== [];
    }

    public function images(): LibraryImages
    {
        return $this->images ??= new LibraryImages;
    }

    /**
     * Une action vient de modifier la bibliothèque (des tags, un ordre) : la
     * lecture partagée est périmée. Renvoie une lecture neuve, que les cartes
     * redessinées dans la même requête utiliseront à leur tour.
     */
    public function refresh(): LibraryImages
    {
        return $this->images = new LibraryImages;
    }
}
