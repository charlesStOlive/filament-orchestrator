<?php

namespace CharlesStOlive\FilamentOrchestrator\Support;

final class ActionType
{
    public const ContentOpen = 'content.open';

    public const ContentClose = 'content.close';

    public const MapLayerShow = 'map.layer.show';

    public const MapLayerHide = 'map.layer.hide';

    public const MapLayerToggle = 'map.layer.toggle';

    public const EventDispatch = 'event.dispatch';

    public const NavigationOpen = 'navigation.open';

    public static function options(): array
    {
        return array_replace([
            self::ContentOpen => 'Afficher un contenu',
            self::ContentClose => 'Fermer le contenu',
            self::MapLayerShow => 'Afficher un layer',
            self::MapLayerHide => 'Masquer un layer',
            self::MapLayerToggle => 'Basculer un layer',
            self::EventDispatch => 'Emettre un evenement',
            self::NavigationOpen => 'Ouvrir une navigation',
        ], config('filament-orchestrator.action_types', []));
    }
}
