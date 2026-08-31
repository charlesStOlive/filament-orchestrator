---
title: Créer et régler un parcours
icon: heroicon-o-map
order: 20
---

# Créer et régler un parcours

La ressource **Parcours interactifs** est le point d'entrée de la gestion globale. Elle rassemble les éléments, déclencheurs et actions d'un scénario.

## Informations principales

- **Schéma** : définit les rôles, événements, actions et paramètres disponibles.
- **Active** : autorise l'utilisation du parcours.
- **Nom** : libellé lisible dans l'administration.
- **Clé stable** : identifiant technique unique. Dans l'application Voyage, elle sert aussi de slug public.
- **Portée d'événement** : espace de noms qui isole les événements de ce parcours.
- **Description** : objectif éditorial et périmètre du scénario.
- **Configuration** : options propres au schéma.
- **État initial** : valeurs disponibles au démarrage du lecteur.

## Choisir le schéma

Le schéma est une définition fournie par l'application. Il répond à ces questions :

- quels types d'éléments peut-on rattacher ?
- quels événements peut-on écouter ?
- quelles actions peut-on exécuter ?
- quels paramètres chaque action accepte-t-elle ?

Changer de schéma après avoir créé des éléments ou des déclencheurs peut rendre leur configuration invalide. Choisissez-le avant de construire le scénario.

## Clé stable et URL publique

La clé doit être courte, explicite et sans dépendre d'un titre susceptible de changer. Exemple :

```text
escales-asie-du-sud-est
```

Ne la modifiez pas après publication sans vérifier les liens externes et les intégrations.

## Portée d'événement

La portée évite qu'un événement provenant d'une autre carte ou d'un autre lecteur déclenche ce parcours. Si elle est vide, une valeur fondée sur l'identifiant du parcours est générée automatiquement.

Renseignez-la manuellement uniquement lorsqu'un composant externe doit connaître une valeur stable à l'avance.

## Configuration et état initial

- La **configuration** contient des réglages permanents du scénario.
- L'**état initial** contient les valeurs chargées au début d'une session, par exemple un chapitre ou une étape courante.

Ces champs transportent des données. Ils ne permettent pas d'écrire des conditions ou du code arbitraire.

## Activer un parcours

Gardez le parcours inactif pendant une refonte importante. Avant activation, vérifiez que ses éléments indispensables sont actifs et que chaque déclencheur possède au moins une action valide.
