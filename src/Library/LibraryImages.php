<?php

namespace CharlesStOlive\FilamentOrchestrator\Library;

use CharlesStOlive\FilamentOrchestrator\Models\LibraryMedia;
use CharlesStOlive\FilamentOrchestrator\Models\Orchestration;
use Illuminate\Support\Collection;

/**
 * Retrouve les images d'une orchestration par leurs tags.
 *
 * C'est la lecture de la bibliothèque : un contenu ne possède pas d'images,
 * il déclare les tags dont il affiche les images (clé `library_tags` de la
 * configuration de son nœud). La bibliothèque est chargée une seule fois par
 * instance, quel que soit le nombre de contenus qui la consultent.
 */
final class LibraryImages
{
    /** Clé, dans la configuration d'un nœud, de la liste des tags dont il affiche les images. */
    public const NODE_CONFIG_KEY = 'library_tags';

    /** @var array<int|string, Collection<int, LibraryMedia>> */
    private array $libraries = [];

    /**
     * Les images qui portent au moins un de ces tags, dans l'ordre de prise de
     * vue (les images sans date à la fin).
     *
     * @param  array<int, string>  $tags
     * @return Collection<int, LibraryMedia>
     */
    public function tagged(Orchestration $orchestration, array $tags): Collection
    {
        $type = $orchestration->libraryTagType();

        return $this->library($orchestration)
            ->filter(fn (LibraryMedia $media): bool => $media->tags
                ->contains(fn ($tag): bool => $tag->type === $type && in_array($tag->name, $tags, true)))
            ->values();
    }

    /**
     * L'image de couverture de chaque tag : la première dans l'ordre de prise
     * de vue. Une seule requête, pour afficher d'un coup toutes les cartes.
     *
     * @return array<string, string> tag => URL de la vignette
     */
    public function coversByTag(Orchestration $orchestration): array
    {
        $type = $orchestration->libraryTagType();
        $covers = [];

        foreach ($this->library($orchestration) as $media) {
            foreach ($media->tags as $tag) {
                if ($tag->type === $type) {
                    $covers[$tag->name] ??= $media->thumbUrl();
                }
            }
        }

        return $covers;
    }

    /** Ce que le navigateur reçoit d'une image : c'est la forme attendue par le player. */
    public function payload(LibraryMedia $media): array
    {
        return [
            'url' => $media->getUrl(),
            'thumb' => $media->thumbUrl(),
            'name' => $media->name,
            'alt' => $media->getCustomProperty('alt', $media->name),
        ];
    }

    /** @return Collection<int, LibraryMedia> */
    private function library(Orchestration $orchestration): Collection
    {
        return $this->libraries[$orchestration->getKey()] ??= $orchestration->libraryMedia()
            ->with('tags')
            ->orderByRaw('taken_at is null')
            ->orderBy('taken_at')
            ->orderBy('order_column')
            ->orderBy('id')
            ->get();
    }
}
