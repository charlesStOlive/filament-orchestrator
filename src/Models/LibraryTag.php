<?php

namespace CharlesStOlive\FilamentOrchestrator\Models;

use Spatie\Tags\Tag;

/**
 * Tag d'une bibliothèque d'images.
 *
 * Spatie stocke le nom d'un tag par langue. Une bibliothèque n'a qu'un
 * vocabulaire, indépendant de la langue de l'interface : la langue est donc
 * figée pour qu'un même nom ne crée jamais deux tags selon la langue active.
 */
class LibraryTag extends Tag
{
    public const LOCALE = 'fr';

    /** Sans cela, Eloquent déduirait « library_tags » du nom de la classe. */
    protected $table = 'tags';

    /** Colonne du pivot `taggables` : Eloquent la déduirait aussi du nom de la classe (« library_tag_id »). */
    public function getForeignKey(): string
    {
        return 'tag_id';
    }

    public static function getLocale()
    {
        return static::LOCALE;
    }
}
