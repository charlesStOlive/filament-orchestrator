<?php

namespace CharlesStOlive\FilamentOrchestrator\Library;

use Carbon\CarbonImmutable;

/**
 * Lit la date de prise de vue et la position GPS dans l'EXIF d'une image.
 *
 * Une photo sans EXIF, ou dans un format qui n'en porte pas, n'est pas une
 * erreur : elle n'a simplement pas de position. Sa date, elle, retombe sur celle
 * du fichier, marquée comme telle (`dateSource`) : pour un fichier envoyé par un
 * navigateur c'est la date de l'envoi, une approximation que l'on ne confond pas
 * avec une vraie prise de vue.
 */
final class MetadataExtractor
{
    public function extract(string $path): MediaMetadata
    {
        if (! is_file($path)) {
            return new MediaMetadata;
        }

        if (str_starts_with((string) @mime_content_type($path), 'video/')) {
            return $this->fromVideo($path);
        }

        $exif = function_exists('exif_read_data') ? @exif_read_data($path) : false;

        if (! is_array($exif)) {
            return $this->withFileDate(new MediaMetadata, $path);
        }

        $latitude = $this->coordinate($exif['GPSLatitude'] ?? null, $exif['GPSLatitudeRef'] ?? null);
        $longitude = $this->coordinate($exif['GPSLongitude'] ?? null, $exif['GPSLongitudeRef'] ?? null);

        // Sans fix, certains appareils écrivent 0, 0 : ce n'est pas une position.
        $located = $latitude !== null && $longitude !== null
            && abs($latitude) <= 90 && abs($longitude) <= 180
            && ($latitude != 0.0 || $longitude != 0.0);

        $takenAt = $this->takenAt($exif);

        return $this->withFileDate(new MediaMetadata(
            takenAt: $takenAt,
            latitude: $located ? $latitude : null,
            longitude: $located ? $longitude : null,
            dateSource: $takenAt ? MediaMetadata::SOURCE_EXIF : null,
        ), $path);
    }

    /**
     * Une vidéo n'a pas d'EXIF : sa date de tournage et sa position sont celles que l'appareil a écrites dans le fichier
     * (voir VideoMetadataReader). Faute de quoi — WebM, montage sans date — c'est la date du fichier, qui pour un fichier
     * chargé par un navigateur est celle du chargement : elle est marquée comme telle et ne rattache rien d'elle-même.
     */
    private function fromVideo(string $path): MediaMetadata
    {
        $video = (new VideoMetadataReader)->read($path);

        if ($video['createdAt'] === null) {
            return $this->withFileDate(new MediaMetadata(latitude: $video['latitude'], longitude: $video['longitude']), $path);
        }

        // La date est en UTC ; une photo garde l'heure « murale » de son EXIF. On convertit vers le fuseau du voyage pour
        // que « 23 h 30 » le 12 reste le 12 (et non le 13 en UTC).
        $timezone = (string) config('filament-orchestrator.library.video_timezone', 'UTC');
        $wall = $video['createdAt']->setTimezone($timezone)->format('Y-m-d H:i:s');

        return new MediaMetadata(
            takenAt: CarbonImmutable::createFromFormat('Y-m-d H:i:s', $wall, 'UTC') ?: null,
            latitude: $video['latitude'],
            longitude: $video['longitude'],
            dateSource: MediaMetadata::SOURCE_VIDEO,
        );
    }

    /** Sans date de prise de vue, la date du fichier en tient lieu. */
    private function withFileDate(MediaMetadata $metadata, string $path): MediaMetadata
    {
        if ($metadata->takenAt !== null || ($modified = @filemtime($path)) === false) {
            return $metadata;
        }

        return new MediaMetadata(
            takenAt: CarbonImmutable::createFromTimestampUTC($modified),
            latitude: $metadata->latitude,
            longitude: $metadata->longitude,
            dateSource: MediaMetadata::SOURCE_FILE,
        );
    }

    private function takenAt(array $exif): ?CarbonImmutable
    {
        foreach (['DateTimeOriginal', 'DateTimeDigitized', 'DateTime'] as $tag) {
            $value = $exif[$tag] ?? null;

            if (! is_string($value) || ! preg_match('/^(\d{4}):(\d{2}):(\d{2}) \d{2}:\d{2}:\d{2}$/', $value, $parts)) {
                continue;
            }

            // « 0000:00:00 00:00:00 » : champ prévu mais jamais renseigné.
            if (! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
                continue;
            }

            return CarbonImmutable::createFromFormat('Y:m:d H:i:s', $value, 'UTC') ?: null;
        }

        return null;
    }

    /**
     * @param  mixed  $parts  [degrés, minutes, secondes], chacun sous la forme « n/d »
     */
    private function coordinate(mixed $parts, mixed $reference): ?float
    {
        if (! is_array($parts) || count($parts) < 3 || ! is_string($reference)) {
            return null;
        }

        [$degrees, $minutes, $seconds] = array_map($this->rational(...), array_values($parts));

        if ($degrees === null || $minutes === null || $seconds === null) {
            return null;
        }

        $value = $degrees + $minutes / 60 + $seconds / 3600;

        return in_array(strtoupper($reference), ['S', 'W'], true) ? -$value : $value;
    }

    private function rational(mixed $value): ?float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (! is_string($value) || ! str_contains($value, '/')) {
            return null;
        }

        [$numerator, $denominator] = array_map('floatval', explode('/', $value, 2));

        return $denominator == 0.0 ? null : $numerator / $denominator;
    }
}
