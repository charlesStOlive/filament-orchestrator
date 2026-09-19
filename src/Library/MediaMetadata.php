<?php

namespace CharlesStOlive\FilamentOrchestrator\Library;

use Carbon\CarbonImmutable;

/**
 * Ce que l'EXIF d'une photo dit d'elle : quand, et où quand la position est connue.
 *
 * Sans date de prise de vue, la date du fichier en tient lieu (`dateSource`
 * vaut alors « file ») : c'est une date approximative, que la bibliothèque
 * n'utilise pas pour rattacher automatiquement une image à une journée.
 */
final readonly class MediaMetadata
{
    public const SOURCE_EXIF = 'exif';

    public const SOURCE_FILE = 'file';

    public const SOURCE_MANUAL = 'manual';

    public function __construct(
        public ?CarbonImmutable $takenAt = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public ?string $dateSource = null,
    ) {}

    public function hasGps(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }
}
