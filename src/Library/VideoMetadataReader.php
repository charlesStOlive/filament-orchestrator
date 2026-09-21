<?php

namespace CharlesStOlive\FilamentOrchestrator\Library;

use Carbon\CarbonImmutable;

/**
 * Lit, dans une vidéo MP4 ou MOV (le format des téléphones), ce que le fichier dit de lui-même : quand il a été
 * tourné et, quand l'appareil l'a écrit, où. Sans `ffmpeg` : ces formats sont des « boîtes » emboîtées dont on ne
 * lit que deux, sans décoder une image.
 *
 *  - `moov/mvhd` : la date de création, en secondes depuis 1904, en UTC ;
 *  - `moov/udta/©xyz` : la position (ISO 6709, « +48.8566+002.3522/ »), écrite par la plupart des téléphones.
 *
 *  - `moov/trak/tkhd` : la largeur et la hauteur de l'image, telles qu'on la voit (une vidéo de téléphone tenue à la
 *    verticale est stockée à l'horizontale et tournée par une matrice : on en tient compte) ;
 *  - `moov/mvhd` encore : la durée, en secondes.
 *
 * Un fichier d'un autre format (WebM, par exemple), illisible ou sans ces informations n'est pas une erreur : rien n'est
 * lu, et l'appelant retombe sur la date du fichier. Une date à zéro (l'outil de montage ne l'a pas écrite) ne vaut rien.
 */
final class VideoMetadataReader
{
    /** Secondes entre 1904-01-01 (l'époque des vidéos MP4/MOV) et 1970-01-01. */
    private const EPOCH_OFFSET = 2082844800;

    /** La boîte `moov` d'une vidéo de 100 Mo pèse quelques centaines de Ko ; on ne lit pas une boîte plus grosse. */
    private const MAX_MOOV_SIZE = 32 * 1024 * 1024;

    /** @return array{createdAt: ?CarbonImmutable, latitude: ?float, longitude: ?float, width: ?int, height: ?int, duration: ?float} */
    public function read(string $path): array
    {
        $moov = $this->moov($path);
        $none = ['createdAt' => null, 'latitude' => null, 'longitude' => null, 'width' => null, 'height' => null, 'duration' => null];

        if ($moov === null) {
            return $none;
        }

        $createdAt = null;
        $position = [null, null];
        $duration = null;
        $size = [null, null];

        foreach ($this->boxes($moov) as [$type, $payload]) {
            if ($type === 'mvhd') {
                $createdAt = $this->creationDate($payload);
                $duration = $this->duration($payload);
            } elseif ($type === 'trak' && $size[0] === null) {
                foreach ($this->boxes($payload) as [$inner, $content]) {
                    if ($inner === 'tkhd') {
                        $size = $this->size($content);
                    }
                }
            } elseif ($type === 'udta') {
                foreach ($this->boxes($payload) as [$inner, $content]) {
                    if ($inner === "\xA9xyz") {
                        $position = $this->position($content);
                    }
                }
            }
        }

        return ['createdAt' => $createdAt, 'latitude' => $position[0], 'longitude' => $position[1], 'width' => $size[0], 'height' => $size[1], 'duration' => $duration];
    }

    /** Le contenu de la boîte `moov`, parcourue depuis le début du fichier sans le lire en entier ; null s'il n'y en a pas. */
    private function moov(string $path): ?string
    {
        $file = @fopen($path, 'rb');

        if ($file === false) {
            return null;
        }

        try {
            $size = (int) filesize($path);
            $offset = 0;

            while ($offset + 8 <= $size) {
                fseek($file, $offset);
                $header = (string) fread($file, 16);

                if (strlen($header) < 8) {
                    return null;
                }

                $length = (int) unpack('N', $header)[1];
                $type = substr($header, 4, 4);
                $headerLength = 8;

                if ($length === 1 && strlen($header) >= 16) {
                    $length = (int) unpack('J', substr($header, 8, 8))[1];
                    $headerLength = 16;
                } elseif ($length === 0) {
                    $length = $size - $offset;
                }

                if ($length < $headerLength) {
                    return null;
                }

                if ($type === 'moov') {
                    $payload = $length - $headerLength;

                    if ($payload > self::MAX_MOOV_SIZE) {
                        return null;
                    }

                    fseek($file, $offset + $headerLength);

                    return $payload === 0 ? null : (string) fread($file, $payload);
                }

                $offset += $length;
            }
        } finally {
            fclose($file);
        }

        return null;
    }

    /**
     * Les boîtes d'un contenu : leur type (quatre octets) et leur charge.
     *
     * @return iterable<int, array{0: string, 1: string}>
     */
    private function boxes(string $data): iterable
    {
        $offset = 0;
        $total = strlen($data);

        while ($offset + 8 <= $total) {
            $length = (int) unpack('N', substr($data, $offset, 4))[1];
            $type = substr($data, $offset + 4, 4);
            $headerLength = 8;

            if ($length === 1 && $offset + 16 <= $total) {
                $length = (int) unpack('J', substr($data, $offset + 8, 8))[1];
                $headerLength = 16;
            } elseif ($length === 0) {
                $length = $total - $offset;
            }

            if ($length < $headerLength || $offset + $length > $total) {
                return;
            }

            yield [$type, substr($data, $offset + $headerLength, $length - $headerLength)];

            $offset += $length;
        }
    }

    /** La date de création de `mvhd` : version 0 (32 bits) ou 1 (64 bits), depuis 1904. */
    private function creationDate(string $mvhd): ?CarbonImmutable
    {
        if ($mvhd === '') {
            return null;
        }

        $version = ord($mvhd[0]);
        $seconds = match (true) {
            $version === 0 && strlen($mvhd) >= 8 => (int) unpack('N', substr($mvhd, 4, 4))[1],
            $version === 1 && strlen($mvhd) >= 12 => (int) unpack('J', substr($mvhd, 4, 8))[1],
            default => 0,
        };

        // Zéro (ou avant 1970) : l'appareil ou l'outil de montage ne l'a pas renseignée.
        if ($seconds <= self::EPOCH_OFFSET) {
            return null;
        }

        $date = CarbonImmutable::createFromTimestampUTC($seconds - self::EPOCH_OFFSET);

        // Une date dans le futur est une horloge déréglée : elle ne vaut pas mieux que rien.
        return $date->greaterThan(CarbonImmutable::now('UTC')->addDay()) ? null : $date;
    }

    /** La durée de `mvhd`, en secondes : l'échelle de temps (version 0 : 32 bits, version 1 : 64 bits), puis la durée. */
    private function duration(string $mvhd): ?float
    {
        if ($mvhd === '') {
            return null;
        }

        $version = ord($mvhd[0]);

        [$scale, $length] = match (true) {
            $version === 0 && strlen($mvhd) >= 20 => [(int) unpack('N', substr($mvhd, 12, 4))[1], (int) unpack('N', substr($mvhd, 16, 4))[1]],
            $version === 1 && strlen($mvhd) >= 32 => [(int) unpack('N', substr($mvhd, 20, 4))[1], (int) unpack('J', substr($mvhd, 24, 8))[1]],
            default => [0, 0],
        };

        return $scale > 0 && $length > 0 ? round($length / $scale, 2) : null;
    }

    /**
     * La largeur et la hauteur de `tkhd`, en pixels (des entiers 16.16), permutées quand la matrice tourne l'image d'un quart
     * de tour. Une piste sans image (le son) a 0 × 0 : rien.
     *
     * @return array{0: ?int, 1: ?int}
     */
    private function size(string $tkhd): array
    {
        if ($tkhd === '') {
            return [null, null];
        }

        // Version 0 : matrice à l'octet 40, taille à 76 ; version 1 (dates et durée sur 64 bits) : 52 et 88.
        $matrix = ord($tkhd[0]) === 1 ? 52 : 40;
        $sizeAt = $matrix + 36;

        if (strlen($tkhd) < $sizeAt + 8) {
            return [null, null];
        }

        $width = (int) unpack('N', substr($tkhd, $sizeAt, 4))[1] >> 16;
        $height = (int) unpack('N', substr($tkhd, $sizeAt + 4, 4))[1] >> 16;

        if ($width < 1 || $height < 1) {
            return [null, null];
        }

        // Les termes a et d de la matrice (des entiers 16.16) : à zéro, l'image est tournée d'un quart de tour.
        $a = (int) unpack('N', substr($tkhd, $matrix, 4))[1];
        $d = (int) unpack('N', substr($tkhd, $matrix + 16, 4))[1];

        return $a === 0 && $d === 0 ? [$height, $width] : [$width, $height];
    }

    /**
     * La position de `©xyz` : deux octets de longueur, deux de langue, puis « +48.8566+002.3522+035.000/ » (ISO 6709).
     *
     * @return array{0: ?float, 1: ?float}
     */
    private function position(string $content): array
    {
        if (strlen($content) < 5 || ! preg_match('/^([+-]\d+(?:\.\d+)?)([+-]\d+(?:\.\d+)?)/', substr($content, 4), $match)) {
            return [null, null];
        }

        [$latitude, $longitude] = [(float) $match[1], (float) $match[2]];

        // Sans fix, certains appareils écrivent 0, 0 : ce n'est pas une position.
        if (abs($latitude) > 90 || abs($longitude) > 180 || ($latitude == 0.0 && $longitude == 0.0)) {
            return [null, null];
        }

        return [$latitude, $longitude];
    }
}
