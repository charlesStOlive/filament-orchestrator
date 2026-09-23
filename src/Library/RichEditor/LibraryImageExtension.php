<?php

namespace CharlesStOlive\FilamentOrchestrator\Library\RichEditor;

use Tiptap\Core\Node;
use Tiptap\Utils\HTML;

/**
 * La référence à des images ou des vidéos de la bibliothèque, dans un texte : « (image 3) », « (vidéos 1, 2, 3) ».
 *
 * Le nœud ne garde que les clés des fichiers (`media`, « 7,8,9 » dans le HTML) et leur genre (`kind`, image ou vidéo),
 * jamais leurs numéros : le numéro est la place du fichier parmi ceux de son genre dans la période, il change quand on
 * les réordonne. L'éditeur (voir resources/js/rich-editor/library-image.js) et la page publique les recalculent donc à
 * chaque affichage : le HTML enregistré est un `span` vide, qui ne porte que les clés et le genre. Une référence d'avant
 * les vidéos (`data-media="7"`, sans genre) se lit comme une image.
 */
class LibraryImageExtension extends Node
{
    /** @var string */
    public static $name = 'libraryImage';

    /** @return array<string, mixed> */
    public function addOptions(): array
    {
        return ['HTMLAttributes' => []];
    }

    /** @return array<array<string, mixed>> */
    public function parseHTML(): array
    {
        return [['tag' => 'span[data-type="'.self::$name.'"]']];
    }

    /** @return array<string, array<string, mixed>> */
    public function addAttributes(): array
    {
        return [
            'media' => [
                'default' => [],
                'parseHTML' => fn ($DOMNode) => self::ids((string) $DOMNode->getAttribute('data-media')),
                'renderHTML' => fn ($attributes) => ['data-media' => implode(',', self::ids(implode(',', (array) ($attributes->media ?? []))))],
            ],
            'kind' => [
                'default' => self::IMAGE,
                'parseHTML' => fn ($DOMNode) => self::kind($DOMNode->getAttribute('data-kind')),
                'renderHTML' => fn ($attributes) => ['data-kind' => self::kind($attributes->kind ?? null)],
            ],
        ];
    }

    public const IMAGE = 'image';

    public const VIDEO = 'video';

    /**
     * Les clés d'une liste « 7,8,9 » : des entiers strictement positifs, sans doublon, dans l'ordre.
     *
     * @return array<int, int>
     */
    public static function ids(string $list): array
    {
        $ids = [];

        foreach (explode(',', $list) as $part) {
            $part = trim($part);

            if (ctype_digit($part) && (int) $part > 0 && ! in_array((int) $part, $ids, true)) {
                $ids[] = (int) $part;
            }
        }

        return $ids;
    }

    /** 'video' ou 'image' : tout autre genre (ou aucun, avant les vidéos) est une image. */
    public static function kind(mixed $kind): string
    {
        return $kind === self::VIDEO ? self::VIDEO : self::IMAGE;
    }

    /**
     * Toujours un `span` vide, quoi que le nœud contienne. Le parseur HTML de tiptap-php n'a pas de notion d'atome : il
     * range dans le nœud tout ce qui se trouve entre ses balises, et le sérialiseur le ressortait. Un HTML mal formé
     * (une balise fermante mal écrite qui laisse le `span` ouvert) suffisait donc à faire avaler au nœud le paragraphe
     * suivant — invisible dans l'éditeur (le nœud y est un atome), mais conservé et réenregistré à chaque sauvegarde.
     * La clé `content` court-circuite le rendu des enfants : le contenu parasite disparaît au prochain enregistrement.
     *
     * @param  object  $node
     * @param  array<string, mixed>  $HTMLAttributes
     * @return array<string, string>
     */
    public function renderHTML($node, $HTMLAttributes = []): array
    {
        $attributes = HTML::mergeAttributes(['data-type' => self::$name], $this->options['HTMLAttributes'], $HTMLAttributes);

        return ['content' => '<span'.HTML::renderAttributes($attributes).'></span>'];
    }
}
