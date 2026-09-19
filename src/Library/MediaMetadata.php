<?php

namespace CharlesStOlive\FilamentOrchestrator\Library;

use Carbon\CarbonImmutable;

/** Ce que l'EXIF d'une photo dit d'elle : quand, et où quand la position est connue. */
final readonly class MediaMetadata
{
    public function __construct(
        public ?CarbonImmutable $takenAt = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
    ) {}

    public function hasGps(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }
}
