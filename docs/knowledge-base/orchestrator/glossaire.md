---
title: Glossaire des parcours
icon: heroicon-o-book-open
order: 80
---

# Glossaire des parcours

## Action

Opération exécutée lorsqu'un déclencheur correspond, par exemple ouvrir un contenu ou déplacer une carte.

## Clé stable

Identifiant technique conservé dans le temps. Les noms peuvent évoluer, mais une clé utilisée par des événements ou intégrations doit rester stable.

## Condition

Comparaison entre un chemin du payload d'événement et une valeur attendue. Toutes les conditions d'un déclencheur doivent correspondre.

## Déclencheur

Règle qui écoute un événement, filtre éventuellement sa source et ses données, puis lance une séquence d'actions.

## Élément ou nœud

Référence, dans le parcours, vers une carte, un point, une couche, un contenu ou un autre modèle métier.

## État initial

Données chargées au démarrage du lecteur et susceptibles d'évoluer pendant l'expérience.

## Événement

Signal produit par l'interface ou un élément, accompagné d'un nom, d'une source et de données.

## Hotpoint

Point géographique jouant le rôle d'élément interactif dans un parcours.

## Nœud polymorphe

Nœud capable de référencer plusieurs classes de modèles selon son rôle.

## Ownership

Mode de rattachement. `owned` réserve l'élément au parcours ; `linked` référence un élément partageable de la bibliothèque.

## Paramètre

Donnée structurée transmise à une action. Son type et ses valeurs possibles sont définis par le schéma.

## Parcours ou orchestration

Instance concrète d'un schéma, composée d'éléments, de déclencheurs et d'actions.

## Payload

Ensemble des données transportées par un événement ou exposées au lecteur.

## Portée d'événement

Espace de noms isolant les événements d'une instance de parcours.

## Rôle

Fonction d'un élément dans le schéma, par exemple `map`, `point`, `layer` ou `content`.

## Schéma

Contrat PHP qui déclare les rôles, événements, actions et paramètres autorisés. L'administrateur choisit parmi ces possibilités sans modifier le code.

## Source et cible

La source produit l'événement écouté. La cible reçoit l'action exécutée.
