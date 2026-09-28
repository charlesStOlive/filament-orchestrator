<?php

namespace CharlesStOlive\FilamentOrchestrator\Library\RichEditor;

use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Support\Facades\FilamentAsset;

/**
 * Apprend à un RichEditor (et à son rendu, RichContentRenderer) la référence à une
 * image de la bibliothèque : on y glisse une image depuis la bibliothèque et
 * « (image 3) » s'écrit là où on la dépose.
 *
 *     RichEditor::make('body')->plugins([LibraryImagePlugin::make()])
 *
 * L'éditeur annonce le dépôt par l'événement Livewire `library-image-dropped`
 * (voir LibraryImageEvent) : c'est à la page de rattacher l'image à son contenu.
 */
class LibraryImagePlugin implements RichContentPlugin
{
    /** Le nom de l'asset JS (voir le service provider) et son paquet. */
    public const ASSET = 'library-image';

    public const ASSET_PACKAGE = 'charlesstolive/filament-orchestrator';

    /** Le fantôme du glisser d'une image (voir resources/js/library-drag.js), chargé sur toutes les pages. */
    public const DRAG_ASSET = 'library-drag';

    public static function make(): static
    {
        return app(static::class);
    }

    public function getTipTapPhpExtensions(): array
    {
        return [app(LibraryImageExtension::class)];
    }

    public function getTipTapJsExtensions(): array
    {
        return [FilamentAsset::getScriptSrc(self::ASSET, self::ASSET_PACKAGE)];
    }

    public function getEditorTools(): array
    {
        return [];
    }

    public function getEditorActions(): array
    {
        return [];
    }
}
