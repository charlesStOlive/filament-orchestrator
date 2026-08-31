<?php

namespace CharlesStOlive\FilamentOrchestrator\Support;

final class TriggerType
{
    public const Click = 'click';

    public const Event = 'event';

    public const Load = 'load';

    public static function options(): array
    {
        return [
            self::Click => 'Au clic',
            self::Event => 'A la reception d’un evenement',
            self::Load => 'Au chargement',
        ];
    }
}
