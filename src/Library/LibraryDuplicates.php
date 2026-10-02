<?php

namespace CharlesStOlive\FilamentOrchestrator\Library;

use CharlesStOlive\FilamentOrchestrator\Models\LibraryMedia;
use CharlesStOlive\FilamentOrchestrator\Models\Orchestration;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reconnaît un fichier déjà chargé dans la bibliothèque d'une orchestration : même nom d'origine, et même date de prise
 * de vue ou même poids.
 *
 * Le nom d'origine est celui du fichier sur l'appareil de qui l'a chargé (`original_name`, gardé par LibraryIngestor) ;
 * pour un média chargé avant, le nom de fichier que medialibrary en a tiré. Le nom seul ne suffit pas (deux appareils
 * numérotent leurs photos de la même façon) : il faut aussi la même date de prise de vue — quand les deux en ont une
 * vraie, lue dans le fichier —, ou le même poids, au octet près.
 */
final class LibraryDuplicates
{
    /** La copie déjà présente de ce fichier, ou null. */
    public function find(Orchestration $orchestration, string $originalName, MediaMetadata $metadata, int $size): ?LibraryMedia
    {
        $originalName = trim($originalName);

        if ($originalName === '') {
            return null;
        }

        /** @var \Illuminate\Support\Collection<int, LibraryMedia> $candidates */
        $candidates = $orchestration->libraryMedia()->getQuery()
            ->where(fn (Builder $query): Builder => $query
                ->where('custom_properties->original_name', $originalName)
                ->orWhere(fn (Builder $query): Builder => $query
                    ->whereNull('custom_properties->original_name')
                    ->where('file_name', self::storedFileName($originalName))))
            ->orderBy('id')
            ->get();

        return $candidates->first(fn (LibraryMedia $media): bool => (int) $media->size === $size
            || ($this->hasFileDate($metadata) && $media->hasReliableDate()
                && $media->taken_at?->format('Y-m-d H:i:s') === $metadata->takenAt->format('Y-m-d H:i:s')));
    }

    /** La clé d'un fichier, pour reconnaître deux fois le même fichier dans un même envoi. */
    public function key(string $originalName, MediaMetadata $metadata, int $size): string
    {
        return trim($originalName).'|'.($this->hasFileDate($metadata) ? $metadata->takenAt->format('Y-m-d H:i:s') : $size);
    }

    /** Une date lue dans le fichier (EXIF, vidéo), et non celle du chargement. */
    private function hasFileDate(MediaMetadata $metadata): bool
    {
        return $metadata->takenAt !== null
            && in_array($metadata->dateSource, [MediaMetadata::SOURCE_EXIF, MediaMetadata::SOURCE_VIDEO], true);
    }

    /** Le nom que medialibrary donne au fichier (voir FileAdder::defaultSanitizer()). */
    private static function storedFileName(string $originalName): string
    {
        return str_replace(['#', '/', '\\', ' '], '-', (string) preg_replace('#\p{C}+#u', '', $originalName));
    }
}
