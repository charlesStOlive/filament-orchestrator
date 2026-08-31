---
title: Tester et dépanner un parcours
icon: heroicon-o-wrench-screwdriver
order: 70
---

# Tester et dépanner un parcours

Testez un parcours progressivement. Une règle simple validée est plus facile à étendre qu'une longue séquence impossible à diagnostiquer.

## Méthode de test

1. Vérifiez les ressources seules : carte, point, couche et contenu.
2. Vérifiez que chaque ressource et chaque nœud est actif.
3. Créez un déclencheur sans condition avec une seule action.
4. Testez avec une source et une cible précises.
5. Ajoutez ensuite les conditions et actions supplémentaires une par une.
6. Testez sur ordinateur et mobile.

## Le parcours public est introuvable

- le parcours est inactif ;
- sa clé ne correspond pas à l'URL ;
- la route publique utilise un autre préfixe ;
- l'utilisateur n'a pas accès à la page selon la configuration de l'application.

## La carte est vide

- le nœud carte est inactif ;
- la carte elle-même est inactive ;
- aucune carte n'est rattachée au rôle attendu ;
- la vue initiale ne couvre pas les données ;
- une couche possède une source invalide.

## Le clic ne déclenche rien

- l'événement choisi ne correspond pas à celui émis ;
- l'élément source précis n'est pas le bon nœud ;
- rôle et clé source ajoutent des contraintes incompatibles ;
- une condition cherche un chemin absent du payload ;
- le déclencheur est inactif.

## L'action ne produit aucun résultat

- l'action est inactive ;
- la cible n'existe pas ou n'a pas le rôle requis ;
- un paramètre obligatoire est vide ou du mauvais type ;
- le gestionnaire client correspondant n'est pas chargé ;
- une action précédente a échoué avec **Arrêter la séquence**.

## Une ressource n'est pas proposée au rattachement

- son modèle ne correspond pas au rôle ;
- elle est propre à un autre parcours ;
- le schéma limite ce rôle à un seul élément ;
- une règle d'ownership interdit son partage.

## Réduire le problème

Dupliquez la règle dans un parcours de test ou désactivez temporairement les actions non essentielles. Ne supprimez pas une configuration de production avant d'avoir identifié la cause.
