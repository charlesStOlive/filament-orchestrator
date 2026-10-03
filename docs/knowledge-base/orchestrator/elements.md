---
title: Éléments, rôles et modes de rattachement
icon: heroicon-o-squares-plus
order: 30
---

# Éléments, rôles et modes de rattachement

Un **élément** est un objet métier utilisé dans le parcours. Techniquement, le parcours conserve un **nœud polymorphe** qui référence cet objet.

## Rôle

Le rôle décrit la fonction de l'élément dans le scénario, pas seulement sa classe technique. Les rôles courants sont :

- `map` : carte principale ou secondaire ;
- `point` : point géographique utilisé comme hotpoint ;
- `layer` : couche pilotée par une action ;
- `content` : contenu narratif à afficher.

Le schéma décide du modèle accepté pour chaque rôle et indique si plusieurs éléments de ce rôle sont autorisés.

## Clé dans le parcours

La clé identifie l'élément au sein du parcours. Elle permet aux événements et actions de retrouver une source ou une cible sans dépendre d'un identifiant numérique.

Utilisez des clés explicites comme `carte-principale`, `bangkok` ou `intro`. Une clé doit rester unique pour un même rôle dans le parcours.

## Propre ou lié

### Propre à cette orchestration (`owned`)

L'élément est réservé à ce parcours. Il ne peut pas être rattaché à un autre parcours comme élément partagé.

Choisissez ce mode pour un contenu ou un objet créé exclusivement pour une expérience.

### Lié depuis la bibliothèque (`linked`)

Le parcours référence un élément réutilisable. Plusieurs parcours peuvent lier la même carte, le même point ou le même contenu, tant qu'il n'est pas déclaré propre ailleurs.

Choisissez ce mode pour les ressources de référence partagées.

## Créer ou rattacher

- **Créer un…** ouvre le formulaire complet de la ressource correspondante. Après enregistrement, le nouvel objet est automatiquement rattaché et vous revenez au parcours.
- **Rattacher un… existant** sélectionne un objet déjà présent dans la bibliothèque.
- **Ouvrir la ressource** accède à l'édition complète de l'objet. Le bouton de retour ramène ensuite au parcours.
- **Modifier le nœud** change uniquement sa clé, son mode, son ordre ou sa configuration dans ce parcours.

## Ordre et état actif

L'ordre sert à présenter ou traiter les éléments de manière déterministe. Un nœud inactif reste configuré mais n'est pas inclus dans le lecteur public.

## Configuration du nœud

Ce champ contient les réglages spécifiques à cet élément dans ce parcours. Il ne modifie pas la ressource d'origine. Utilisez-le uniquement pour les options prévues par le schéma ou le lecteur.

## L'apparence d'un hotpoint

Un hotpoint garde l'apparence de son **type de point** (forme, icône, couleur). Le parcours peut la compléter sans créer un type par cas :

- **sa couleur**, donnée au point par le parcours, l'emporte sur celle du type ;
- **une image** de la bibliothèque du parcours (par exemple l'image de une de l'étape) peut lui être proposée. Elle n'apparaît, en mini-vignette, que si le type du point a pour contenu **Image** ; les autres types l'ignorent. Elle est relue à chaque affichage : changer l'image de une change le point, sans rien resynchroniser.

## Pourquoi un élément n'est-il pas proposé ?

- son modèle ne correspond pas au rôle ;
- il est déjà réservé comme élément propre d'un autre parcours ;
- le schéma n'autorise qu'un seul élément pour ce rôle ;
- il est déjà rattaché avec une combinaison incompatible.
