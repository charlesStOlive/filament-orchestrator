<?php

namespace CharlesStOlive\FilamentOrchestrator\Models;

use Illuminate\Database\Eloquent\Builder;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
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
    use HasTags;

    protected function casts(): array
    {
        return [
            'taken_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
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
