<?php

namespace CharlesStOlive\FilamentOrchestrator\Library;

use Carbon\CarbonImmutable;

/**
 * Lit la date de prise de vue et la position GPS dans l'EXIF d'une image.
 *
 * Une photo sans EXIF, ou dans un format qui n'en porte pas, n'est pas une
 * erreur : elle n'a simplement ni date ni position. On ne retombe volontairement
 * pas sur la date du fichier, qui est celle de l'envoi et non de la prise de vue.
 */
final class MetadataExtractor
{
    public function extract(string $path): MediaMetadata
    {
        if (! function_exists('exif_read_data') || ! is_file($path)) {
            return new MediaMetadata;
        }

        $exif = @exif_read_data($path);

        if (! is_array($exif)) {
            return new MediaMetadata;
        }

        $latitude = $this->coordinate($exif['GPSLatitude'] ?? null, $exif['GPSLatitudeRef'] ?? null);
        $longitude = $this->coordinate($exif['GPSLongitude'] ?? null, $exif['GPSLongitudeRef'] ?? null);

        // Sans fix, certains appareils écrivent 0, 0 : ce n'est pas une position.
        $located = $latitude !== null && $longitude !== null
            && abs($latitude) <= 90 && abs($longitude) <= 180
            && ($latitude != 0.0 || $longitude != 0.0);

        return new MediaMetadata(
            takenAt: $this->takenAt($exif),
            latitude: $located ? $latitude : null,
            longitude: $located ? $longitude : null,
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
