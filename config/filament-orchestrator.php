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
     * The image library of an orchestration: every image lives on the
     * orchestration itself, and tags say where it is used.
     */
    'library' => [
        'disk' => 'public',

        /*
         * The library needs the media and tag models of this package (extra
         * columns on `media`, a fixed tag language). They are swapped in only
         * while the application still uses the Spatie defaults; set to false
         * to declare your own `media-library.media_model` and `tags.tag_model`.
         */
        'register_models' => true,

        /*
         * Thumbnails are generated during the upload by default: a queued
         * conversion never runs when no queue worker is listening.
         */
        'queue_conversions' => false,

        /*
         * Classes implementing Library\Contracts\LibraryTagger. Each one adds
         * automatic tags to an image as it enters the library.
         */
        'taggers' => [],

        /*
         * Classes implementing Library\Contracts\LibraryTagLabeler. They give
         * a readable label to technical tags (e.g. "day:3f9c…" shown as "J1").
         */
        'labelers' => [],

        /*
         * Classes extending Library\LibraryAction. Each one adds an action to the
         * selection menu of the library, and can mark images with an icon.
         */
        'actions' => [],
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
