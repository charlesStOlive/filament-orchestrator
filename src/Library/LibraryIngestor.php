<?php

namespace CharlesStOlive\FilamentOrchestrator\Library;

use CharlesStOlive\FilamentOrchestrator\Library\Contracts\LibraryTagger;
use CharlesStOlive\FilamentOrchestrator\Models\LibraryMedia;
use CharlesStOlive\FilamentOrchestrator\Models\Orchestration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Spatie\MediaLibrary\MediaCollections\FileAdder;

/**
 * Le seul chemin par lequel une image entre dans la bibliothèque d'une
 * orchestration, quel que soit l'uploader : elle est stockée, sa date et sa
 * position sont lues dans l'EXIF, puis les tags sont posés.
 *
 * Le fichier d'origine n'est jamais supprimé : nettoyer un envoi temporaire
 * reste l'affaire de l'appelant.
 */
final class LibraryIngestor
{
    public function __construct(private readonly MetadataExtractor $extractor) {}

    /** @param UploadedFile|string $file Un fichier envoyé, ou un chemin local absolu. */
    public function ingest(
        Orchestration $orchestration,
        UploadedFile|string $file,
        ?IngestContext $context = null,
    ): LibraryMedia {
        $path = $file instanceof UploadedFile ? (string) $file->getRealPath() : $file;

        $media = $orchestration->addMedia($file)->preservingOriginal();

        return $this->store($orchestration, $media, $this->extractor->extract($path), $context ?? new IngestContext);
    }

    /** Pour un fichier déjà posé sur un disque, par exemple par un FileUpload Filament. */
    public function ingestFromDisk(
        Orchestration $orchestration,
        string $disk,
        string $path,
        ?IngestContext $context = null,
    ): LibraryMedia {
        $metadata = $this->extractor->extract(Storage::disk($disk)->path($path));

        $media = $orchestration->addMediaFromDisk($path, $disk)->preservingOriginal();

        return $this->store($orchestration, $media, $metadata, $context ?? new IngestContext);
    }

    /**
     * Une image dont le fichier vit ailleurs, sur le web : elle est téléchargée telle quelle (les conversions —
     * vignette, tailles d'affichage — s'en font comme pour un fichier envoyé), avec son copyright et l'adresse
     * d'origine gardés à part. Sans EXIF à lire : ni date de prise de vue ni position, à saisir à la main si besoin.
     */
    public function ingestExternalImage(
        Orchestration $orchestration,
        string $url,
        ?string $copyright = null,
        ?IngestContext $context = null,
    ): LibraryMedia {
        $adder = $orchestration->addMediaFromUrl($url, LibraryMedia::IMAGE_TYPES);

        return $this->store($orchestration, $adder, new MediaMetadata, $context ?? new IngestContext, array_filter([
            'origin' => 'external_image',
            'external_url' => $url,
            'copyright' => filled($copyright) ? trim($copyright) : null,
            // Sans quoi la fiche affiche « lue dans l'EXIF » (le défaut de `dateSourceLabel()`) pour un fichier qui n'en a pas.
            'date_source' => MediaMetadata::SOURCE_FILE,
        ]));
    }

    /**
     * Une vidéo YouTube : sans API, on n'en garde que l'ID, extrait de n'importe quel lien de partage. Le fichier
     * qui la porte dans la bibliothèque n'est que sa vignette officielle — il n'y a pas de vidéo à héberger.
     */
    public function ingestYoutube(
        Orchestration $orchestration,
        string $urlOrId,
        ?IngestContext $context = null,
    ): LibraryMedia {
        $videoId = YoutubeUrl::id($urlOrId);

        if ($videoId === null) {
            throw new InvalidArgumentException('Lien YouTube non reconnu.');
        }

        $adder = $orchestration->addMediaFromUrl(YoutubeUrl::thumbnailUrl($videoId), LibraryMedia::IMAGE_TYPES);

        return $this->store($orchestration, $adder, new MediaMetadata, $context ?? new IngestContext, [
            'origin' => 'youtube',
            'youtube_id' => $videoId,
            'youtube_url' => YoutubeUrl::watchUrl($videoId),
            'date_source' => MediaMetadata::SOURCE_FILE,
        ]);
    }

    /** @param array<string, mixed> $extraProperties Posées en plus de celles tirées des métadonnées (EXIF…). */
    private function store(
        Orchestration $orchestration,
        FileAdder $adder,
        MediaMetadata $metadata,
        IngestContext $context,
        array $extraProperties = [],
    ): LibraryMedia {
        /** @var LibraryMedia $media */
        $media = $adder
            ->withCustomProperties([
                ...array_filter([
                    'source' => $context->source,
                    'date_source' => $metadata->dateSource,
                    // Une vidéo : sa taille et sa durée, quand le fichier les dit (voir VideoMetadataReader).
                    'width' => $metadata->width,
                    'height' => $metadata->height,
                    'duration' => $metadata->duration,
                ]),
                ...$extraProperties,
            ])
            ->toMediaCollection(Orchestration::LIBRARY_COLLECTION);

        $media->forceFill([
            'taken_at' => $metadata->takenAt,
            'latitude' => $metadata->latitude,
            'longitude' => $metadata->longitude,
        ])->save();

        $this->tag($media, $orchestration, $context);

        return $media;
    }

    private function tag(LibraryMedia $media, Orchestration $orchestration, IngestContext $context): void
    {
        $tags = $context->tags;

        foreach ((array) config('filament-orchestrator.library.taggers', []) as $class) {
            $tagger = app($class);

            if ($tagger instanceof LibraryTagger) {
                $tags = [...$tags, ...$tagger->tags($media, $orchestration, $context)];
            }
        }

        $tags = array_values(array_unique(array_filter(array_map('trim', $tags), 'strlen')));

        if ($tags !== []) {
            $media->attachTags($tags, $orchestration->libraryTagType());
        }
    }
}
