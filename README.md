# Filament Orchestrator

`filament-orchestrator` décrit des expériences interactives sans stocker de code exécutable en base.

Une expérience associe des sources et des déclencheurs à des actions ordonnées. Le premier adaptateur fourni écoute les clics de `filament-map` et sait :

- afficher ou fermer un contenu texte/image ;
- afficher, masquer ou basculer un layer ;
- émettre un événement public ;
- ouvrir une navigation contrôlée.

## Affichage

```blade
<livewire:filament-orchestrator-player :experience="$experience" />
```

L’application parente reste responsable des modèles métier, des autorisations et des événements Laravel qu’elle souhaite exposer.

## Contrat cartographique

Le runtime écoute `filament-map:point-clicked`. Une action cartographique émet ensuite `filament-map:command` avec `mapId`, `scope`, `command` et `target`.

Les types d’action supplémentaires peuvent être ajoutés à la liste d’administration avec `filament-orchestrator.action_types`. Leur exécuteur navigateur devra être enregistré dans une version ultérieure du registre d’adaptateurs.
