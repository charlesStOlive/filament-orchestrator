---
title: Déclencheurs, événements et actions
icon: heroicon-o-bolt
order: 40
---

# Déclencheurs, événements et actions

Un déclencheur répond à la phrase : **quand cet événement arrive depuis cette source et respecte ces conditions, exécuter ces actions dans cet ordre**.

## Configurer le déclencheur

- **Nom** : description lisible, par exemple « Clic sur Bangkok ».
- **Clé stable** : identifiant technique unique du déclencheur.
- **Événement** : signal attendu, choisi parmi ceux du schéma.
- **Élément source précis** : nœud exact qui doit produire l'événement.
- **Ou rôle source** : accepte tout élément du rôle indiqué.
- **Ou clé source** : filtre la source par sa clé stable.
- **Conditions** : filtres appliqués aux données de l'événement.
- **Ordre** : priorité entre déclencheurs.
- **Actif** : permet de suspendre la règle sans la supprimer.

## Choisir la source

Préférez **Élément source précis** lorsqu'une règle concerne un seul hotpoint. Utilisez le rôle lorsque tous les éléments de cette famille doivent partager la même réaction.

Les contraintes renseignées sont cumulatives. Une source doit respecter l'identifiant précis, le rôle et la clé lorsqu'ils sont présents. Évitez donc de remplir plusieurs filtres sans nécessité.

## Conditions

Les conditions lisent le payload de l'événement. La clé est un chemin, et la valeur est celle attendue.

Exemple :

```text
point.slug = bangkok
```

Un chemin imbriqué utilise des points. Toutes les conditions doivent correspondre. Une liste de valeurs attendues signifie « l'une de ces valeurs ».

Les conditions filtrent des données ; elles n'exécutent pas de code et ne prennent pas en charge des expressions libres.

## Ajouter des actions

Chaque action possède :

- un **nom** facultatif pour l'administration ;
- une **clé** unique dans le déclencheur ;
- une **action** autorisée par le schéma ;
- une cible précise, un rôle cible ou une clé cible ;
- des paramètres générés selon la définition de l'action ;
- une stratégie en cas d'erreur ;
- un état actif.

## Cible précise ou générique

- **Élément cible** : meilleur choix lorsqu'une action doit agir sur un seul contenu, point, calque ou carte.
- **Rôle cible** : cible un élément selon sa fonction lorsque le gestionnaire d'action le prévoit.
- **Clé cible** : cible par identifiant stable dans le parcours.

Le schéma limite automatiquement les choix au rôle compatible avec l'action.

## Paramètres

Les champs affichés dépendent de l'action : texte, nombre, interrupteur, liste de choix, liste de valeurs ou objet clé/valeur. Un paramètre est une donnée transmise à l'action, jamais du code.

## Ordre et gestion des erreurs

Les actions sont exécutées de haut en bas.

- **Continuer** : les actions suivantes sont tentées même si celle-ci échoue.
- **Arrêter la séquence** : aucune action suivante n'est exécutée après l'erreur.

Utilisez l'arrêt lorsqu'une action suivante dépend absolument du succès de la précédente.
