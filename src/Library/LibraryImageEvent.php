<?php

namespace CharlesStOlive\FilamentOrchestrator\Library;

/**
 * Les événements qui font parler la bibliothèque, ses panneaux d'images et
 * l'éditeur de texte d'une page sans qu'ils se connaissent.
 *
 * Livewire (il traverse les composants, le serveur s'en mêle) :
 *  - DROPPED : une image de la bibliothèque vient d'être déposée dans l'éditeur de texte
 *    (`media` : sa clé). La page qui l'écoute la rattache au contenu qu'elle édite.
 *
 * Navigateur (`window`, pour des effets purement visuels) :
 *  - HOVER : l'image `media` est survolée (`on`) ou ne l'est plus, dans le texte ou dans un panneau.
 *    Chacun met en évidence l'autre : la référence du texte, la vignette du panneau.
 */
final class LibraryImageEvent
{
    public const DROPPED = 'library-image-dropped';

    public const HOVER = 'library-image-hover';
}
