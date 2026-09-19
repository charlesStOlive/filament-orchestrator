<?php

namespace CharlesStOlive\FilamentOrchestrator\Library;

/**
 * D'où vient l'envoi. Un uploader lancé depuis la journée « J3 » passe le tag
 * de cette journée : les photos y sont rattachées dès leur arrivée, sans que
 * la bibliothèque ait à deviner quoi que ce soit.
 */
final readonly class IngestContext
{
    /** @param array<int, string> $tags Tags posés sur toutes les images de l'envoi. */
    public function __construct(
        public array $tags = [],
        public ?string $source = null,
    ) {}
}
