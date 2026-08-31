---
title: Comprendre un parcours interactif
icon: heroicon-o-light-bulb
order: 10
---

# Comprendre un parcours interactif

Un parcours interactif relie des éléments existants et décrit leurs réactions. Il permet par exemple d'ouvrir un contenu lorsqu'un visiteur clique sur un point d'une carte.

## Le modèle en cinq parties

1. **Schéma** : catalogue des rôles, événements et actions autorisés par l'application.
2. **Parcours** ou **orchestration** : scénario concret créé depuis ce schéma.
3. **Éléments** ou **nœuds** : cartes, hotpoints, couches, contenus ou autres objets utilisés dans le scénario.
4. **Déclencheurs** : règles qui attendent un événement précis.
5. **Actions** : opérations exécutées, dans l'ordre, lorsqu'un déclencheur correspond.

## Exemple simple

Pour « au clic sur Bangkok, ouvrir la fiche Bangkok » :

- le schéma autorise le rôle `point`, l'événement de clic et l'action d'ouverture ;
- le parcours représente le voyage en Asie ;
- Bangkok est rattaché comme élément de rôle `point` ;
- la fiche Bangkok est rattachée comme élément de rôle `content` ;
- un déclencheur écoute le clic provenant de Bangkok ;
- son action cible la fiche Bangkok.

## Ce qui est configuré en base

La base conserve des clés, relations, conditions et paramètres. Elle ne contient pas de code PHP ou JavaScript exécutable. Le comportement disponible est défini par le schéma et par les composants de l'application.

Cette séparation apporte deux garanties :

- l'administrateur compose uniquement des opérations prévues ;
- le développeur peut faire évoluer l'implémentation sans réécrire chaque parcours.

## Ordre conseillé de travail

1. Créez les cartes, points et contenus nécessaires.
2. Créez le parcours et choisissez son schéma.
3. Rattachez les éléments au parcours.
4. Créez les déclencheurs.
5. Ajoutez et ordonnez leurs actions.
6. Testez chaque interaction sur la page publique.

Ne commencez pas par les déclencheurs : leurs sources et leurs cibles doivent déjà exister dans le parcours.
