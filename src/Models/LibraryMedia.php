<?php

namespace CharlesStOlive\FilamentOrchestrator\Models;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
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

    /** 'image' ou 'video'. */
    public function kind(): string
    {
        return $this->isVideo() ? 'video' : 'image';
    }

    public function scopeVideos(Builder $query): Builder
    {
        return $query->where('mime_type', 'like', 'video/%');
    }

    public function scopeImages(Builder $query): Builder
    {
        return $query->where('mime_type', 'not like', 'video/%');
    }

    /** La vignette quand elle existe (sinon l'original, le temps qu'elle soit générée). */
    public function thumbUrl(): string
    {
        return $this->hasGeneratedConversion('thumb') ? $this->getUrl('thumb') : $this->getUrl();
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
