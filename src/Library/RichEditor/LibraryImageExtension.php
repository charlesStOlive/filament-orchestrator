<?php

namespace CharlesStOlive\FilamentOrchestrator\Library\RichEditor;

use Tiptap\Core\Node;
use Tiptap\Utils\HTML;

/**
 * La référence à une image de la bibliothèque, dans un texte : « (image 3) ».
 *
 * Le nœud ne garde que la clé de l'image (`media`), jamais son numéro : le
 * numéro est sa place parmi les images de la période, il change quand on les
 * réordonne. L'éditeur (voir resources/js/rich-editor/library-image.js) et la
 * page publique le recalculent donc à chaque affichage : le HTML enregistré est
 * un `span` vide, qui ne porte que la clé.
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
                'parseHTML' => fn ($DOMNode) => is_numeric($DOMNode->getAttribute('data-media')) ? (int) $DOMNode->getAttribute('data-media') : null,
                'renderHTML' => fn ($attributes) => ['data-media' => $attributes->media ?? null],
            ],
        ];
    }

    /**
     * @param  object  $node
     * @param  array<string, mixed>  $HTMLAttributes
     * @return array<mixed>
     */
    public function renderHTML($node, $HTMLAttributes = []): array
    {
        return [
            'span',
            HTML::mergeAttributes(['data-type' => self::$name], $this->options['HTMLAttributes'], $HTMLAttributes),
        ];
    }
}
