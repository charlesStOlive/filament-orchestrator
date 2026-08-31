---
title: Contenus narratifs
icon: heroicon-o-photo
order: 50
---

# Contenus narratifs

Un contenu narratif rassemble le texte, les images et les boutons affichés pendant une expérience. Il peut être réutilisé dans plusieurs parcours ou réservé à un seul.

## Champs du contenu

- **Nom interne** : nom utilisé par les administrateurs.
- **Clé de bibliothèque** : identifiant technique unique et stable.
- **Titre affiché** : titre vu par le visiteur.
- **Texte** : corps principal du contenu.
- **Images** : médias associés, réordonnables.
- **Boutons** : commandes proposées au visiteur.
- **Actif** : contrôle l'inclusion du contenu dans le lecteur.

Le nom interne peut contenir des précisions de gestion qui ne doivent pas apparaître publiquement. Le titre affiché doit être rédigé pour le visiteur.

## Images

Les images sont enregistrées dans la médiathèque et peuvent être réordonnées. Utilisez des fichiers suffisamment légers pour un affichage mobile. Le nom et les métadonnées du média peuvent être utilisés pour l'accessibilité selon le rendu de l'application.

## Boutons

Chaque bouton possède :

- une **clé stable** : valeur technique envoyée lors de l'interaction ;
- un **libellé** : texte affiché au visiteur.

Exemples de clés : `continuer`, `retour`, `voir-carte`. Deux boutons d'un même contenu ne doivent pas avoir la même clé.

Le bouton émet un événement. La réaction à ce bouton est configurée dans les déclencheurs du parcours, pas directement dans le contenu.

## Réutiliser ou réserver

Rattachez le contenu en mode **lié** s'il constitue une fiche de référence commune. Utilisez le mode **propre** si son texte et ses boutons n'ont de sens que dans un parcours précis.

## Avant publication

- Vérifiez le titre et le texte sur mobile.
- Contrôlez l'ordre et le poids des images.
- Testez chaque bouton dans le parcours.
- Vérifiez que le contenu et son nœud sont actifs.
- Évitez de modifier une clé de bouton déjà utilisée par un déclencheur.
