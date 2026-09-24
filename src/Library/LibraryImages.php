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

    /**
     * Le type de données que porte un glisser-déposer d'image ou de vidéo de la bibliothèque
     * (voir `dataTransfer`) : la carte qu'on glisse l'écrit, la mini-grille d'une
     * période et l'éditeur de texte le lisent. Le contenu est du JSON, avec un élément par
     * carte glissée (toutes les cartes cochées, si celle qu'on saisit l'est) :
     * `{"orchestration": 3, "items": [{"media": 12, "kind": "image"}, {"media": 13, "kind": "video"}]}`.
     */
    public const DRAG_TYPE = 'application/x-orchestrator-library-image';

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
     * Ajoute l'image à l'ensemble que désignent ces tags, à la fin : après la
     * dernière image, même si d'autres n'ont jamais été placées. Une image déjà
     * dans l'ensemble garde sa place.
     *
     * @param  array<int, string>  $tags
     * @return bool Vrai si l'image vient d'être ajoutée.
     */
    public function append(Orchestration $orchestration, array $tags, LibraryMedia $media): bool
    {
        $type = $orchestration->libraryTagType();
        $before = $this->tagged($orchestration, $tags)->map(fn (LibraryMedia $other): int => $other->getKey());

        if ($before->contains($media->getKey())) {
            return false;
        }

        $media->attachTags($tags, $type);
        unset($this->libraries[$orchestration->getKey()]);

        $this->reorder($orchestration, $tags, [...$before->all(), $media->getKey()]);

        return true;
    }

    /**
     * Fait de cette image la seule de l'ensemble que désignent ces tags : les autres le quittent (elles restent dans la
     * bibliothèque). C'est le cas d'un ensemble d'une image, comme celle de « une » d'une période.
     *
     * @param  array<int, string>  $tags
     */
    public function replace(Orchestration $orchestration, array $tags, LibraryMedia $media): void
    {
        $type = $orchestration->libraryTagType();

        foreach ($this->tagged($orchestration, $tags) as $other) {
            if (! $other->is($media)) {
                $other->detachTags($tags, $type);
            }
        }

        $media->attachTags($tags, $type);
        unset($this->libraries[$orchestration->getKey()]);
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
            // Une couverture est une image : une vidéo n'a pas de vignette.
            $first = $this->tagged($orchestration, [$name])->first(fn (LibraryMedia $media): bool => $media->isImage());

            if ($first !== null) {
                $covers[$name] = $first->thumbUrl();
            }
        }

        return $covers;
    }

    /**
     * Ce que le navigateur reçoit d'une image : c'est la forme attendue par le
     * player. `header` marque la première image d'un ensemble.
     */
    public function payload(LibraryMedia $media, bool $header = false): array
    {
        if ($media->isYoutube()) {
            return $this->youtubePayload($media);
        }

        if ($media->isVideo()) {
            return $this->videoPayload($media);
        }

        $dimensions = $media->dimensions();

        return [
            'type' => 'image',
            // La clé de l'image : stable, elle survit aux changements d'ordre. C'est par elle que le texte
            // d'un contenu désigne une image (voir Library\RichEditor\LibraryImageExtension).
            'id' => $media->getKey(),
            'url' => $media->getUrl(),
            'thumb' => $media->thumbUrl(),
            'medium' => $media->conversionUrl('medium'),
            'large' => $media->conversionUrl('large'),
            'width' => $dimensions['width'] ?? null,
            'height' => $dimensions['height'] ?? null,
            'name' => $media->name,
            'alt' => $media->getCustomProperty('alt', $media->name),
            'caption' => $media->getCustomProperty('caption'),
            'copyright' => $media->copyright(),
            'header' => $header,
        ];
    }

    /**
     * Ce que le navigateur reçoit d'une vidéo YouTube : son identifiant et l'adresse à mettre dans un iframe pour la
     * lire. La vignette officielle, elle, a bien été téléchargée comme un fichier de la bibliothèque : ses
     * conversions (`thumb`/`medium`/`large`) existent réellement, gardées ici au même titre que celles d'une image
     * — par prudence, pour qui n'aurait pas encore de branche à part pour `type: 'youtube'` — même si l'affichage
     * normal d'une vidéo YouTube passe par l'iframe (`embedUrl`), pas par ces images.
     *
     * @return array<string, mixed>
     */
    private function youtubePayload(LibraryMedia $media): array
    {
        $dimensions = $media->dimensions();

        return [
            'type' => 'youtube',
            'id' => $media->getKey(),
            'youtubeId' => $media->youtubeId(),
            'embedUrl' => $media->youtubeEmbedUrl(),
            'thumb' => $media->thumbUrl(),
            'medium' => $media->conversionUrl('medium'),
            'large' => $media->conversionUrl('large'),
            'width' => $dimensions['width'] ?? null,
            'height' => $dimensions['height'] ?? null,
            'name' => $media->name,
            'alt' => $media->getCustomProperty('alt', $media->name),
            'caption' => $media->getCustomProperty('caption'),
        ];
    }

    /**
     * Ce que le navigateur reçoit d'une vidéo : son fichier, tel quel. Elle n'a ni vignette ni taille d'affichage
     * (aucun traitement côté serveur) ; le navigateur en tire l'aperçu. Sa taille et sa durée, quand le fichier les dit,
     * donnent aux cadres leur format sans attendre.
     *
     * @return array<string, mixed>
     */
    private function videoPayload(LibraryMedia $media): array
    {
        $dimensions = $media->dimensions();

        return [
            'type' => 'video',
            'id' => $media->getKey(),
            'url' => $media->getUrl(),
            // L'adresse à lire : le fichier entier, ou seulement le passage choisi (`#t=début,fin`).
            'src' => $media->trimmedUrl(),
            'mime' => $media->mime_type,
            'width' => $dimensions['width'] ?? null,
            'height' => $dimensions['height'] ?? null,
            'duration' => $media->duration(),
            'trim' => $media->trim(),
            'name' => $media->name,
            'alt' => $media->getCustomProperty('alt', $media->name),
            'caption' => $media->getCustomProperty('caption'),
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
