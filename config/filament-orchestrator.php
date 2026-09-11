<?php

return [
    'cluster' => [
        'enabled' => false,
        'class' => null,
    ],

    'tables' => [
        'orchestrations' => 'filament_orchestrator_orchestrations',
        'nodes' => 'filament_orchestrator_nodes',
        'contents' => 'filament_orchestrator_contents',
        'triggers' => 'filament_orchestrator_triggers',
        'actions' => 'filament_orchestrator_actions',
    ],

    'resources' => [
        'orchestrations' => true,
        'contents' => true,
    ],

    'node_management' => [
        'layout' => 'grouped',
        'roles' => [],
    ],

    /*
     * The application owns its use cases. Each class listed here describes
     * the available node roles, events and actions for one orchestration type.
     */
    'schemas' => [],

    /*
     * Automations are Filament resources extending AutomationResource: they
     * declare their own form, table, pages and projection. Only list the
     * classes here — the plugin registers them on the panel.
     */
    'automations' => [],
];
