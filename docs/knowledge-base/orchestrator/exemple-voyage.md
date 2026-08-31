---
title: "Exemple : rendre une carte interactive"
icon: heroicon-o-cursor-arrow-rays
order: 60
---

# Exemple : rendre une carte interactive

Ce scénario ouvre une fiche narrative lorsqu'un visiteur clique sur le hotpoint Bangkok.

## Préparer les ressources

1. Créez une carte et vérifiez son aperçu.
2. Créez le point géographique Bangkok avec ses coordonnées.
3. Rattachez Bangkok à la carte.
4. Créez un contenu narratif avec le titre et le texte de Bangkok.

## Créer le parcours

1. Ouvrez **Parcours interactifs**.
2. Créez un parcours avec le schéma cartographique proposé par l'application.
3. Donnez-lui un nom et une clé stable.
4. Gardez-le inactif pendant la construction si la route publique est déjà connue.

## Ajouter les éléments

Dans les onglets du parcours :

1. Rattachez la carte avec le rôle **Carte** et la clé `carte-principale`.
2. Rattachez Bangkok avec le rôle **Hotpoint** et la clé `bangkok`.
3. Rattachez la fiche avec le rôle **Contenu** et la clé `bangkok`.

Les deux éléments peuvent partager la même clé car leurs rôles sont différents. Ce choix facilite la lecture du scénario.

## Créer le déclencheur

1. Ajoutez un déclencheur nommé « Clic sur Bangkok ».
2. Choisissez l'événement de clic sur un point.
3. Sélectionnez le nœud `point · bangkok` comme source précise.
4. Laissez les conditions vides : la source précise suffit.

## Ajouter l'action

1. Dans le déclencheur, ajoutez une action.
2. Choisissez l'action d'ouverture ou d'affichage de contenu proposée par le schéma.
3. Sélectionnez `content · bangkok` comme cible.
4. Complétez uniquement les paramètres demandés.
5. Laissez **Continuer** en cas d'erreur s'il n'y a pas d'action suivante dépendante.

## Tester

1. Activez la carte, le point, le contenu, leurs nœuds, le déclencheur et l'action.
2. Activez le parcours.
3. Ouvrez sa page publique.
4. Cliquez sur Bangkok.
5. Vérifiez l'ouverture du bon contenu et le comportement sur mobile.

Si rien ne se passe, retirez temporairement les conditions, vérifiez la source exacte puis contrôlez la cible.
