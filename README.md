# Filament Orchestrator

## Documentation intégrée à Filament

Le package dépend de `guava/filament-knowledge-base` et embarque une documentation opérateur dans `docs/knowledge-base`.
L'application hôte peut l'ajouter à sa base de connaissances avec :

```bash
php artisan vendor:publish --tag=filament-orchestrator-docs --force
```

Les ressources Parcours interactifs et Contenus narratifs implémentent `HasKnowledgeBase`. Le plugin compagnon Guava affiche donc automatiquement les articles correspondants dans leur menu d'aide.

`filament-orchestrator` centralise des scénarios interactifs sans stocker de
PHP ni de JavaScript exécutable en base de données.

## Architecture

Une orchestration est composée de cinq concepts :

- un **schéma PHP** décrit les rôles de nœuds, les événements et les actions autorisés ;
- une **orchestration SQL** configure une instance de ce schéma ;
- ses **nœuds polymorphes** relient cartes, hotpoints, couches, contenus ou modèles métier ;
- ses **déclencheurs** écoutent un événement et filtrent éventuellement une source ;
- chaque déclencheur possède des **actions SQL ordonnées**, dont seuls les paramètres sont en JSON.

Le schéma appartient à l’application. Le package fournit le moteur, les
modèles génériques, le contenu de base et les écrans Filament.

## Cluster Filament

Les ressources ne sont pas regroupées par défaut. Une application peut les
ranger dans son propre cluster :

```php
FilamentOrchestratorPlugin::make()->cluster(Voyage::class);
```

```php
return [
    'schemas' => [
        App\Orchestrator\Schemas\MapContentSchema::class,
    ],
];
```

Un schéma étend `OrchestratorSchema` et déclare :

- `nodes()` avec des `NodeDefinition` ;
- `events()` avec des `EventDefinition` ;
- `actions()` avec des `ActionDefinition` et leurs `ParameterDefinition`.

## Tables

- `filament_orchestrator_orchestrations` : instance, schéma, scope, configuration et état initial ;
- `filament_orchestrator_nodes` : rôle, clé, relation polymorphe, mode `owned` ou `linked` ;
- `filament_orchestrator_contents` : contenu de base, boutons et médias Spatie ;
- `filament_orchestrator_triggers` : événement, source et conditions ;
- `filament_orchestrator_actions` : action, cible, paramètres, ordre et stratégie d’erreur.

Chaque action est une ligne SQL. Le JSON est réservé aux paramètres,
conditions et états : les séquences restent donc filtrables,
ordonnables et éditables individuellement.

## Affichage

```blade
<livewire:filament-orchestrator-player :orchestration="$orchestration" />
```

## Extension JavaScript

Les actions sont exécutées par un registre. Une application peut ajouter une
action sans modifier le package :

```js
window.addEventListener('filament-orchestrator:ready', ({ detail }) => {
    detail.manager.registerAction('map.flashFeature', ({ services, target, action, event }) => {
        services.mapCommand('flash-feature', target, action.parameters, event)
    })
})
```

`filament-map` possède le registre complémentaire :

```js
window.addEventListener('filament-map:ready', ({ detail }) => {
    detail.manager.registerCommand('flash-feature', ({ instance, detail: command }) => {
        // Utiliser ici l’API Leaflet de l’instance.
        return true
    })
})
```

La base ne conserve que les clés stables `map.flashFeature` et
`flash-feature`, jamais le code des fonctions.
