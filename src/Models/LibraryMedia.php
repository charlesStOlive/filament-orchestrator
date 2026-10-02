<?php

namespace CharlesStOlive\FilamentOrchestrator\Models;

use Carbon\CarbonImmutable;
use CharlesStOlive\FilamentOrchestrator\Library\VideoMetadataReader;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Spatie\Image\Enums\CropPosition;
use Spatie\MediaLibrary\Conversions\Conversion;
use Spatie\MediaLibrary\Conversions\ConversionCollection;
use Spatie\MediaLibrary\Conversions\FileManipulator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Spatie\Tags\HasTags;

/**
 * Une image de la bibliothèque d'une orchestration.
 *
 * C'est le modèle Media de l'application (voir `media_model` de la config
 * medialibrary) : les autres modèles qui portent des médias n'en voient que
 * trois colonnes nullables de plus.
 *
 * Les tags sont rangés par type, un type par bibliothèque (voir
 * Orchestration::libraryTagType()) : deux voyages ne partagent jamais un tag.
 */
class LibraryMedia extends Media
{
    use HasTags {
        HasTags::tags as private baseTags;
    }

    /** Les images acceptées dans la bibliothèque. */
    public const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    /**
     * Les vidéos acceptées. Le serveur n'en fait rien (ni conversion ni aperçu : cela demanderait `ffmpeg`) : le fichier
     * est servi tel quel et le navigateur le lit. Le format est donc celui que les navigateurs savent lire.
     */
    public const VIDEO_TYPES = ['video/mp4', 'video/webm', 'video/quicktime'];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    /**
     * La date de prise de vue : l'heure « murale » de l'appareil (celle de l'EXIF), gardée telle quelle, sans fuseau.
     *
     * Elle ne passe volontairement pas par le cast `datetime` d'Eloquent, qui passe par la façade `Date` : une application
     * peut y brancher `Date::useCallable()` pour ramener les dates au fuseau de l'utilisateur (c'est ce que fait le paquet
     * des permissions), et l'heure serait alors décalée à l'écriture — 22 h 03 le 5 mai enregistrée à 00 h 03 le 6 —
     * dans une requête web seulement, pas en console. Un objet `Carbon` construit ici n'y est pas soumis.
     *
     * @return Attribute<CarbonImmutable|null, mixed>
     */
    protected function takenAt(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?CarbonImmutable => $value === null ? null : CarbonImmutable::parse($value, 'UTC'),
            set: fn (mixed $value): ?string => match (true) {
                $value === null || $value === '' => null,
                $value instanceof DateTimeInterface => $value->format('Y-m-d H:i:s'),
                default => CarbonImmutable::parse((string) $value, 'UTC')->format('Y-m-d H:i:s'),
            },
        );
    }

    /** Une vidéo, et non une image : elle n'a ni vignette ni taille d'affichage, seulement son fichier. */
    public function isVideo(): bool
    {
        return str_starts_with((string) $this->mime_type, 'video/');
    }

    public function isImage(): bool
    {
        return ! $this->isVideo();
    }

    /**
     * D'où vient le fichier : `'upload'` (un fichier envoyé, le cas normal), `'external_image'` (une URL
     * d'image extérieure, avec son copyright) ou `'youtube'` (une vidéo YouTube, dont on ne garde que l'ID —
     * le fichier qui la porte n'est que sa vignette officielle, téléchargée une fois).
     */
    public function origin(): string
    {
        return (string) $this->getCustomProperty('origin', 'upload');
    }

    public function isExternalImage(): bool
    {
        return $this->origin() === 'external_image';
    }

    public function isYoutube(): bool
    {
        return $this->origin() === 'youtube';
    }

    /** Une vraie vidéo locale ou une vidéo YouTube : l'affichage « sans recadrage » les traite pareil. */
    public function isPlayable(): bool
    {
        return $this->isVideo() || $this->isYoutube();
    }

    public function youtubeId(): ?string
    {
        $id = $this->getCustomProperty('youtube_id');

        return is_string($id) && $id !== '' ? $id : null;
    }

    public function youtubeUrl(): ?string
    {
        $url = $this->getCustomProperty('youtube_url');

        return is_string($url) && $url !== '' ? $url : null;
    }

    public function youtubeEmbedUrl(): ?string
    {
        $id = $this->youtubeId();

        return $id !== null ? "https://www.youtube.com/embed/{$id}" : null;
    }

    public function externalUrl(): ?string
    {
        $url = $this->getCustomProperty('external_url');

        return is_string($url) && $url !== '' ? $url : null;
    }

    public function copyright(): ?string
    {
        $copyright = $this->getCustomProperty('copyright');

        return is_string($copyright) && $copyright !== '' ? $copyright : null;
    }

    /**
     * La coupe d'une vidéo, en secondes : où le lecteur commence et où il s'arrête. Non destructive — le fichier reste
     * entier sur le disque —, le lecteur s'en tient à ce passage (fragment `#t=début,fin`). Un côté peut manquer.
     *
     * @return array{start: float|null, end: float|null}
     */
    public function trim(): array
    {
        $start = $this->getCustomProperty('trim_start');
        $end = $this->getCustomProperty('trim_end');

        return [
            'start' => is_numeric($start) && $start > 0 ? (float) $start : null,
            'end' => is_numeric($end) && $end > 0 ? (float) $end : null,
        ];
    }

    /** L'adresse du fichier vidéo, avec sa coupe : `…mp4#t=12,45`. Sans coupe, l'adresse seule. */
    public function trimmedUrl(): string
    {
        ['start' => $start, 'end' => $end] = $this->trim();

        if ($start === null && $end === null) {
            return $this->getUrl();
        }

        return $this->getUrl().'#t='.($start ?? 0).($end === null ? '' : ','.$end);
    }

    /**
     * Les cadrages d'une image : la partie qu'on garde quand un affichage la recadre (`object-fit: cover`, et la
     * vignette carrée `thumb`, fabriquée d'après lui). Clé → libellé, icône (la flèche qui pointe vers la partie
     * gardée), point d'ancrage en % (`object-position`) et position de découpe de la vignette. Non destructif,
     * comme la coupe d'une vidéo : le fichier reste entier.
     */
    public const FOCUSES = [
        'top-left' => ['label' => 'En haut à gauche', 'icon' => 'heroicon-m-arrow-up-left', 'x' => 0, 'y' => 0, 'crop' => CropPosition::TopLeft],
        'top' => ['label' => 'En haut', 'icon' => 'heroicon-m-arrow-up', 'x' => 50, 'y' => 0, 'crop' => CropPosition::Top],
        'top-right' => ['label' => 'En haut à droite', 'icon' => 'heroicon-m-arrow-up-right', 'x' => 100, 'y' => 0, 'crop' => CropPosition::TopRight],
        'left' => ['label' => 'À gauche', 'icon' => 'heroicon-m-arrow-left', 'x' => 0, 'y' => 50, 'crop' => CropPosition::Left],
        'center' => ['label' => 'Au centre', 'icon' => 'heroicon-m-arrows-pointing-in', 'x' => 50, 'y' => 50, 'crop' => CropPosition::Center],
        'right' => ['label' => 'À droite', 'icon' => 'heroicon-m-arrow-right', 'x' => 100, 'y' => 50, 'crop' => CropPosition::Right],
        'bottom-left' => ['label' => 'En bas à gauche', 'icon' => 'heroicon-m-arrow-down-left', 'x' => 0, 'y' => 100, 'crop' => CropPosition::BottomLeft],
        'bottom' => ['label' => 'En bas', 'icon' => 'heroicon-m-arrow-down', 'x' => 50, 'y' => 100, 'crop' => CropPosition::Bottom],
        'bottom-right' => ['label' => 'En bas à droite', 'icon' => 'heroicon-m-arrow-down-right', 'x' => 100, 'y' => 100, 'crop' => CropPosition::BottomRight],
    ];

    public const FOCUS_DEFAULT = 'center';

    /** Le cadrage choisi (une clé de FOCUSES) ; le centre tant qu'on n'en a pas choisi. */
    public function focus(): string
    {
        $focus = $this->getCustomProperty('focus');

        return is_string($focus) && isset(self::FOCUSES[$focus]) ? $focus : self::FOCUS_DEFAULT;
    }

    /**
     * Le point d'ancrage du cadrage, en % : `object-position` en CSS, et ce que le navigateur reçoit (voir
     * LibraryImages::payload()).
     *
     * @return array{x: int, y: int}
     */
    public function focusPoint(): array
    {
        ['x' => $x, 'y' => $y] = self::FOCUSES[$this->focus()];

        return ['x' => $x, 'y' => $y];
    }

    /** `object-position` en CSS : « 50% 0% » pour un cadrage en haut. */
    public function objectPosition(): string
    {
        ['x' => $x, 'y' => $y] = $this->focusPoint();

        return "{$x}% {$y}%";
    }

    /**
     * La découpe de la vignette carrée quand elle suit un cadrage (voir Orchestration::registerMediaConversions()) :
     * réduite par son petit côté (`landscape` : par la hauteur), l'image est découpée en carré du côté du cadrage. Null
     * au centre (la découpe d'origine), pour une vidéo, ou tant que la taille de l'image n'est pas gardée.
     *
     * Elle ne lit que les propriétés du média, jamais le fichier : on est appelé pendant l'inventaire des conversions,
     * que la lecture d'un fichier de conversion relancerait. setFocus() garde la taille avant de refaire la vignette.
     *
     * @return array{landscape: bool, position: CropPosition}|null
     */
    public function thumbCrop(): ?array
    {
        $width = (int) $this->getCustomProperty('width', 0);
        $height = (int) $this->getCustomProperty('height', 0);

        if ($this->isVideo() || $this->focus() === self::FOCUS_DEFAULT || $width < 1 || $height < 1) {
            return null;
        }

        return [
            'landscape' => $width >= $height,
            'position' => self::FOCUSES[$this->focus()]['crop'],
        ];
    }

    /**
     * Enregistre un cadrage, et refait la vignette qui le suit. Le centre (celui d'origine) n'est pas gardé : l'image
     * revient à l'affichage par défaut.
     */
    public function setFocus(string $focus): void
    {
        $focus = isset(self::FOCUSES[$focus]) ? $focus : self::FOCUS_DEFAULT;

        if ($focus === $this->focus()) {
            return;
        }

        if ($focus === self::FOCUS_DEFAULT) {
            $this->forgetCustomProperty('focus');
        } else {
            $this->setCustomProperty('focus', $focus);
        }

        $this->save();

        // La taille, gardée dans les propriétés au passage : thumbCrop() la lit là.
        $this->dimensions();

        // Tout de suite, même quand les conversions passent par la file d'attente : on vient de choisir le cadrage, la
        // vignette doit le montrer à la réponse.
        if ($this->isImage() && $this->hasGeneratedConversion('thumb')) {
            app(FileManipulator::class)->performConversions(
                ConversionCollection::createForMedia($this)->filter(fn (Conversion $conversion): bool => $conversion->getName() === 'thumb'),
                $this,
            );
        }
    }

    /** D'où vient la date de prise de vue, pour le dire à qui la lit. */
    public function dateSourceLabel(): string
    {
        return match ($this->getCustomProperty('date_source', 'exif')) {
            'manual' => 'Saisie à la main',
            'file' => 'Date du chargement : le fichier ne porte pas de date de prise de vue',
            'video' => 'Date de tournage, lue dans la vidéo',
            default => 'Date de prise de vue, lue dans l’EXIF',
        };
    }

    /** 'image' ou 'video' — un YouTube (pas de fichier vidéo réel) rejoint 'video' : c'en est une pour qui la regarde. */
    public function kind(): string
    {
        return $this->isPlayable() ? 'video' : 'image';
    }

    public function scopeVideos(Builder $query): Builder
    {
        return $query->where('mime_type', 'like', 'video/%');
    }

    public function scopeImages(Builder $query): Builder
    {
        return $query->where('mime_type', 'not like', 'video/%');
    }

    /** Les vidéos YouTube : leur propre type, filtrable et groupable à part des vraies vidéos et des images. */
    public function scopeYoutube(Builder $query): Builder
    {
        return $query->where('custom_properties->origin', 'youtube');
    }

    /** Les images dont le fichier vient d'une URL extérieure : leur propre type, à part des images envoyées. */
    public function scopeExternalImages(Builder $query): Builder
    {
        return $query->where('custom_properties->origin', 'external_image');
    }

    /** Une image envoyée par un uploader, ni YouTube ni image externe : le sens strict de « Images » dans la bibliothèque. */
    public function scopeUploadedImages(Builder $query): Builder
    {
        return $query->images()->where(fn (Builder $q): Builder => $q
            ->whereNull('custom_properties->origin')
            ->orWhereNotIn('custom_properties->origin', ['youtube', 'external_image']));
    }

    /** Les conversions d'une image : la vignette et les tailles d'affichage (voir Orchestration::registerMediaConversions()). */
    public const CONVERSIONS = ['thumb', 'medium', 'large'];

    /**
     * Au-delà, une image dont les conversions manquent n'est plus « en cours d'optimisation » : leur fabrication a
     * échoué (fichier illisible, file d'attente arrêtée). Elle s'affiche par son original, sans faire patienter.
     */
    public const OPTIMIZING_MINUTES = 30;

    /**
     * Une image dont la vignette ou une taille d'affichage n'est pas encore fabriquée : les conversions passent par la
     * file d'attente (`filament-orchestrator.library.queue_conversions`), l'image s'affiche en attendant par son
     * original. Une vidéo n'en a aucune : elle n'est jamais en cours d'optimisation.
     */
    public function isOptimizing(): bool
    {
        if (! $this->isImage() || $this->created_at === null || $this->created_at->lt(now()->subMinutes(self::OPTIMIZING_MINUTES))) {
            return false;
        }

        foreach (self::CONVERSIONS as $conversion) {
            if (! $this->hasGeneratedConversion($conversion)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Les images en cours d'optimisation (voir isOptimizing()), au plus près en SQL : récentes, images, sans leurs
     * trois conversions.
     */
    public function scopeOptimizing(Builder $query): Builder
    {
        return $query->images()
            ->where('created_at', '>=', now()->subMinutes(self::OPTIMIZING_MINUTES))
            ->where(function (Builder $query): void {
                foreach (self::CONVERSIONS as $conversion) {
                    $query->orWhereNull("generated_conversions->{$conversion}")
                        ->orWhere("generated_conversions->{$conversion}", false);
                }
            });
    }

    /** Le nom du fichier sur l'appareil de qui l'a chargé ; pour un média plus ancien, celui que medialibrary en a tiré. */
    public function originalName(): string
    {
        $name = $this->getCustomProperty('original_name');

        return is_string($name) && $name !== '' ? $name : (string) $this->file_name;
    }

    /** La vignette quand elle existe (sinon l'original, le temps qu'elle soit générée). */
    public function thumbUrl(): string
    {
        if (! $this->hasGeneratedConversion('thumb')) {
            return $this->getUrl();
        }

        // La vignette suit le cadrage (thumbCrop()) : son adresse le dit, pour qu'un navigateur ne garde pas en cache
        // celle d'un autre cadrage. Au centre, l'adresse d'origine.
        $focus = $this->focus();

        return $this->getUrl('thumb').($focus === self::FOCUS_DEFAULT ? '' : '?focus='.$focus);
    }

    /** Une taille d'affichage quand elle existe (sinon l'original, le temps qu'elle soit générée). */
    public function conversionUrl(string $conversion): string
    {
        return $this->hasGeneratedConversion($conversion) ? $this->getUrl($conversion) : $this->getUrl();
    }

    /**
     * Largeur et hauteur de l'image telle qu'elle s'affiche, ou null quand on
     * ne peut pas les lire (disque distant, fichier absent).
     *
     * Elles sont lues sur la plus grande conversion — déjà orientée selon
     * l'EXIF, contrairement à l'original — puis gardées dans les propriétés
     * du média : la lecture d'une page ne rouvre plus jamais un fichier.
     *
     * @return array{width: int, height: int}|null
     */
    public function dimensions(): ?array
    {
        $width = (int) $this->getCustomProperty('width', 0);
        $height = (int) $this->getCustomProperty('height', 0);

        if ($width > 0 && $height > 0) {
            return ['width' => $width, 'height' => $height];
        }

        if ($this->isVideo()) {
            return $this->readVideoFacts()['dimensions'];
        }

        $conversion = $this->hasGeneratedConversion('large') ? 'large' : null;
        $path = $this->getPath($conversion ?? '');
        $size = is_file($path) ? @getimagesize($path) : false;

        if ($size === false || $size[0] < 1 || $size[1] < 1) {
            return null;
        }

        // Sans conversion, l'original peut porter une orientation EXIF qui
        // échange largeur et hauteur : on ne mémorise alors rien de fiable.
        if ($conversion === null) {
            return ['width' => $size[0], 'height' => $size[1]];
        }

        $this->setCustomProperty('width', $size[0])->setCustomProperty('height', $size[1]);
        $this->saveQuietly();

        return ['width' => $size[0], 'height' => $size[1]];
    }

    /**
     * La durée d'une vidéo, en secondes, quand le fichier la dit (MP4, MOV) ; null sinon (WebM) ou pour une image.
     */
    public function duration(): ?float
    {
        if (! $this->isVideo()) {
            return null;
        }

        $duration = $this->getCustomProperty('duration');

        return is_numeric($duration) && $duration > 0 ? (float) $duration : $this->readVideoFacts()['duration'];
    }

    /**
     * La taille et la durée d'une vidéo chargée avant qu'on les lise : relues dans le fichier une fois, puis gardées dans
     * les propriétés du média — comme la taille d'une image —, pour que l'ouverture d'une page ne rouvre plus jamais le
     * fichier. Un fichier qui ne les dit pas (WebM) n'est relu qu'une fois : l'échec est gardé aussi.
     *
     * @return array{dimensions: array{width: int, height: int}|null, duration: float|null}
     */
    private function readVideoFacts(): array
    {
        if ($this->getCustomProperty('facts_read')) {
            $width = (int) $this->getCustomProperty('width', 0);
            $height = (int) $this->getCustomProperty('height', 0);
            $duration = $this->getCustomProperty('duration');

            return [
                'dimensions' => $width > 0 && $height > 0 ? ['width' => $width, 'height' => $height] : null,
                'duration' => is_numeric($duration) && $duration > 0 ? (float) $duration : null,
            ];
        }

        $path = $this->getPath();
        $read = is_file($path) ? (new VideoMetadataReader)->read($path) : ['width' => null, 'height' => null, 'duration' => null];

        foreach (['width', 'height', 'duration'] as $key) {
            $read[$key] === null ? $this->forgetCustomProperty($key) : $this->setCustomProperty($key, $read[$key]);
        }

        $this->setCustomProperty('facts_read', true)->saveQuietly();

        return [
            'dimensions' => $read['width'] !== null && $read['height'] !== null ? ['width' => $read['width'], 'height' => $read['height']] : null,
            'duration' => $read['duration'],
        ];
    }

    /** Les tags, avec leur position dans l'ensemble qu'ils désignent (voir la colonne `sort` du pivot). */
    public function tags(): MorphToMany
    {
        return $this->baseTags()->withPivot('sort');
    }

    /**
     * La date est-elle celle de la prise de vue (EXIF) ou saisie à la main, plutôt
     * que celle du fichier ? Une image plus ancienne que la colonne
     * `date_source` est réputée datée par son EXIF (ou, pour une vidéo, par ses métadonnées).
     */
    public function hasReliableDate(): bool
    {
        return $this->getCustomProperty('date_source', 'exif') !== 'file';
    }

    public function hasGps(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /** @return array<int, string> */
    public function tagNames(?string $type = null): array
    {
        return $this->tagsWithType($type)->pluck('name')->all();
    }

    public function scopeInLibrary(Builder $query): Builder
    {
        return $query->where('collection_name', Orchestration::LIBRARY_COLLECTION);
    }

    public function scopeTakenOn(Builder $query, string $date): Builder
    {
        return $query->whereDate('taken_at', $date);
    }

    public function scopeWithGps(Builder $query): Builder
    {
        return $query->whereNotNull('latitude')->whereNotNull('longitude');
    }

    /**
     * Photos autour d'un point. Le filtre est un carré englobant, pas un
     * cercle : c'est portable (SQLite comme MySQL), utilise l'index
     * latitude/longitude, et l'écart avec un vrai rayon est sans importance
     * pour trier des photos de voyage.
     */
    public function scopeNear(Builder $query, float $latitude, float $longitude, float $kilometers): Builder
    {
        $latitudeDelta = $kilometers / 111.32;
        $longitudeDelta = $kilometers / (111.32 * max(cos(deg2rad($latitude)), 0.01));

        return $query
            ->whereBetween('latitude', [$latitude - $latitudeDelta, $latitude + $latitudeDelta])
            ->whereBetween('longitude', [$longitude - $longitudeDelta, $longitude + $longitudeDelta]);
    }
}
