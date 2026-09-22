<?php

namespace CharlesStOlive\FilamentOrchestrator\Library\RichEditor;

use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Support\Facades\FilamentAsset;

/**
 * Nettoie ce qui arrive par un copier-coller (Word, une page web, Google Docs…) dans un RichEditor :
 * tout ce que l'éditeur ne sait pas produire lui-même (polices, couleurs, tailles, marges, classes…)
 * est retiré avant d'entrer dans le texte, qui ne garde que sa structure — gras, italique, titres,
 * listes, liens. Les images collées sont retirées aussi : elles ne s'importent jamais par ce biais.
 *
 * Combiner avec `->fileAttachments(false)` : ce plugin nettoie le HTML collé, mais c'est
 * `fileAttachments(false)` qui empêche Filament de brancher son propre import d'images (bouton,
 * glisser-déposer, images encodées en base64) — sans lui, une image glissée passerait à côté de ce
 * nettoyage, qui ne voit que le collage.
 *
 *     RichEditor::make('body')->fileAttachments(false)->plugins([PasteCleanupPlugin::make()])
 */
class PasteCleanupPlugin implements RichContentPlugin
{
    public const ASSET = 'paste-cleanup';

    public const ASSET_PACKAGE = 'charlesstolive/filament-orchestrator';

    public static function make(): static
    {
        return app(static::class);
    }

    public function getTipTapPhpExtensions(): array
    {
        return [];
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
