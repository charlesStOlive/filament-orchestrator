<?php

namespace CharlesStOlive\FilamentOrchestrator\Library;

use CharlesStOlive\FilamentOrchestrator\Models\LibraryMedia;
use CharlesStOlive\FilamentOrchestrator\Models\Orchestration;
use Illuminate\Support\Collection;

/**
 * Retrouve les images d'une orchestration par leurs tags, dans leur ordre.
 *
 * C'est la lecture de la bibliothèque : un contenu ne possède pas d'images,
 * il déclare les tags dont il affiche les images (clé `library_tags` de la
 * configuration de son nœud). La bibliothèque est chargée une seule fois par
 * instance, quel que soit le nombre de contenus qui la consultent.
 *
 * L'ordre d'un ensemble est celui que l'on a donné en réordonnant (la position
 * est portée par le pivot du tag : voir `reorder()`), puis, pour ce qui n'a pas
 * été placé, celui de la prise de vue. La première image d'un ensemble en est
 * l'en-tête.
 */
final class LibraryImages
{
    /** Clé, dans la configuration d'un nœud, de la liste des tags dont il affiche les images. */
    public const NODE_CONFIG_KEY = 'library_tags';

    /** L'icône de l'image d'en-tête, la première de son ensemble. */
    public const HEADER_ICON = 'heroicon-s-star';

    /** @var array<int|string, Collection<int, LibraryMedia>> */
    private array $libraries = [];

    /**
     * Les images qui portent au moins un de ces tags, dans l'ordre de l'ensemble.
     *
     * @param  array<int, string>  $tags
     * @return Collection<int, LibraryMedia>
     */
    public function tagged(Orchestration $orchestration, array $tags): Collection
    {
        $type = $orchestration->libraryTagType();

        return $this->library($orchestration)
            ->filter(fn (LibraryMedia $media): bool => $this->positions($media, $type, $tags) !== [])
            ->sortBy(fn (LibraryMedia $media): array => [
                min($this->positions($media, $type, $tags)),
                $media->taken_at?->getTimestamp() ?? PHP_INT_MAX,
                $media->getKey(),
            ])
            ->values();
    }

    /**
     * L'image d'en-tête d'un ensemble : la première.
     *
     * @param  array<int, string>  $tags
     */
    public function header(Orchestration $orchestration, array $tags): ?LibraryMedia
    {
        return $this->tagged($orchestration, $tags)->first();
    }

    /**
     * Range les images d'un ensemble dans cet ordre. Celles qui ne sont pas
     * citées (arrivées entre-temps) suivent, dans leur ordre actuel ; des
     * identifiants étrangers à l'ensemble sont ignorés.
     *
     * @param  array<int, string>  $tags
     * @param  array<int, int|string>  $orderedIds
     */
    public function reorder(Orchestration $orchestration, array $tags, array $orderedIds): void
    {
        $type = $orchestration->libraryTagType();
        $current = $this->tagged($orchestration, $tags)->keyBy(fn (LibraryMedia $media): int => $media->getKey());

        $ordered = collect($orderedIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $current->has($id))
            ->unique()
            ->concat($current->keys()->diff($orderedIds)->values())
            ->unique()
            ->values();

        foreach ($ordered as $position => $id) {
            $media = $current->get($id);

            foreach ($media->tags as $tag) {
                if ($tag->type === $type && in_array($tag->name, $tags, true)) {
                    $media->tags()->updateExistingPivot($tag->getKey(), ['sort' => $position]);
                }
            }
        }

        unset($this->libraries[$orchestration->getKey()]);
    }

    /**
     * Fait de cette image l'en-tête de l'ensemble : la première, les autres
     * gardant leur ordre.
     *
     * @param  array<int, string>  $tags
     */
    public function moveToFirst(Orchestration $orchestration, array $tags, LibraryMedia $media): void
    {
        $others = $this->tagged($orchestration, $tags)
            ->reject(fn (LibraryMedia $other): bool => $other->is($media))
            ->map(fn (LibraryMedia $other): int => $other->getKey());

        $this->reorder($orchestration, $tags, [$media->getKey(), ...$others->all()]);
    }

    /**
     * L'image de couverture de chaque tag : la première de son ensemble. Une
     * seule requête, pour afficher d'un coup toutes les cartes.
     *
     * @return array<string, string> tag => URL de la vignette
     */
    public function coversByTag(Orchestration $orchestration): array
    {
        $type = $orchestration->libraryTagType();
        $names = $this->library($orchestration)
            ->flatMap(fn (LibraryMedia $media): array => $media->tags->where('type', $type)->pluck('name')->all())
            ->unique();

        $covers = [];

        foreach ($names as $name) {
            $covers[$name] = $this->tagged($orchestration, [$name])->first()->thumbUrl();
        }

        return $covers;
    }

    /**
     * Ce que le navigateur reçoit d'une image : c'est la forme attendue par le
     * player. `header` marque la première image d'un ensemble.
     */
    public function payload(LibraryMedia $media, bool $header = false): array
    {
        return [
            'url' => $media->getUrl(),
            'thumb' => $media->thumbUrl(),
            'name' => $media->name,
            'alt' => $media->getCustomProperty('alt', $media->name),
            'header' => $header,
        ];
    }

    /**
     * Les positions de l'image dans les ensembles que désignent ces tags : la
     * position posée en réordonnant, ou, faute de mieux, la fin.
     *
     * @param  array<int, string>  $tags
     * @return array<int, int>
     */
    private function positions(LibraryMedia $media, string $type, array $tags): array
    {
        return $media->tags
            ->filter(fn ($tag): bool => $tag->type === $type && in_array($tag->name, $tags, true))
            ->map(fn ($tag): int => $tag->pivot->sort ?? PHP_INT_MAX)
            ->values()
            ->all();
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
