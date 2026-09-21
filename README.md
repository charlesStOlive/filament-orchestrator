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

## Automatisations

Construire une orchestration à la main convient aux cas uniques. Pour un
parcours répétitif, une **automatisation** part d'un formulaire simplifié et
fabrique le graphe complet : saisir une étape crée son hotpoint, son contenu,
et le câblage qui les relie.

Une automatisation est une ressource Filament ordinaire qui étend
`AutomationResource`. Elle déclare son `form()`, sa `table()`, ses
`getPages()`, et une méthode `projection()` qui dit ce que la saisie fabrique :

```php
class VoyageResource extends AutomationResource
{
    protected static string $automationKey = 'voyage';
    protected static string $automationSchema = MapContentSchema::class;

    public static function projection(MapContentProjection $projection): MapContentProjection
    {
        return $projection
            ->scene(field: 'map_scene_id')
            ->each('days', function (MapContentItem $day): void {
                $day->point(name: 'label', latitude: 'latitude', longitude: 'longitude');
                $day->content(title: 'title', body: 'body', libraryTags: ['day:'.$day->automationId()]);
                $day->whenPointClicked()->opensContent()->movesMapToPoint(zoom: 8);
            }, keyFrom: 'label');
    }
}
```

Seule la classe est déclarée en configuration ; le plugin l'enregistre sur le
panneau :

```php
return ['automations' => [App\Orchestrator\Automations\VoyageResource::class]];
```

### Où vit chaque chose

Le moteur ne connaît que des nœuds et des événements : `Projection`, `Item`,
`NodeDeclaration` et `Wiring` n'offrent que des primitives — `node()`, `on()`,
`run()`. Les méthodes parlantes comme `point()` ou `whenPointClicked()`
encodent un vocabulaire précis (`map.point.clicked`, `map.moveTo`) qui
appartient au schéma : celui-ci les livre en retournant ses propres
sous-classes depuis `projectionClass()`, et elles vivent à côté de lui. Chacune
ne fait qu'une à trois lignes au-dessus d'une primitive, et `trigger()` reste
là pour écrire un déclencheur entier à la main.

### Ce que le moteur garantit

- **Matérialisation progressive** : un nœud déclaré avec `when()` n'existe que
  si ses champs sont renseignés, et le câblage qui le viserait s'efface tant
  qu'il manque. Une étape se complète donc par étapes, sans jamais produire de
  graphe invalide.
- **Édition manuelle préservée** : la synchronisation ne crée, ne met à jour et
  ne supprime que les nœuds portant sa propre clé d'automatisation. Ce qui a
  été ajouté à la main dans l'orchestration survit à toute régénération.
- **Nettoyage** : retirer une ligne du formulaire supprime les nœuds qu'elle
  avait produits, et avec eux les modèles dont elle était propriétaire.

## Bibliothèque d'images et de vidéos

Les images (et les vidéos, voir plus bas) d'une orchestration ne sont pas rangées dans ses contenus : elles
vivent toutes **sur l'orchestration**, dans sa bibliothèque (collection Spatie
`library`). Ce qui rattache une image à une journée ou à une introduction, ce
sont ses **tags**. Un contenu ne possède donc pas d'images, il déclare les tags
dont il affiche les images — `NodeDeclaration::library([...])` — et le payload
lit la bibliothèque à l'affichage. Rien n'est copié.

Chaque image porte sa date de prise de vue et sa position GPS (lues dans
l'EXIF) dans de vraies colonnes de la table `media`, ce qui permet de les
trier, filtrer et grouper.

- **Entrer une image** : toujours par `Library\LibraryIngestor`, quel que soit
  l'uploader. Il stocke, lit l'EXIF, puis pose les tags — ceux du
  `IngestContext` (par exemple le tag de la journée depuis laquelle on
  envoie) et ceux des `LibraryTagger` listés dans
  `filament-orchestrator.library.taggers`.
- **Envoyer** : `MediaUploadAction` est l'uploader, distinct de la gestion. Il
  verse des images dans la bibliothèque et pose les tags qu'on lui donne :
  `MediaUploadAction::make()->record($voyage)->tags(['day:3f9c…'])`. Il émet
  ensuite l'événement Livewire `orchestrator-library-updated`, que les
  composants qui affichent la bibliothèque écoutent pour se rafraîchir.
- **Gérer** : `MediaLibraryAction` ouvre `MediaLibraryTable`, une grille de
  cartes avec filtres (date, tags, GPS, autour d'un point), groupements (date,
  zone), actions groupées de tag, et trois tailles de vignettes S / M / L (S : petits carrés à icônes ; le choix est gardé en session). Elle s'ajoute comme n'importe quelle
  action : `MediaLibraryAction::make()->record($this->record)`.
- **Version légère** : `TagImagesPanel` est un composant Livewire à glisser
  dans l'écran d'un contenu (une journée, une introduction) : les images qui
  portent ses tags, un envoi qui les étiquette d'emblée, et un accès à la
  bibliothèque complète — ouverte « au service » de ces tags avec
  `MediaLibraryAction::make()->focusTags([...])` — pour en rattacher d'autres
  par lot ou n'afficher que celles de la journée.
- **Lire** : `Library\LibraryImages` retrouve les images d'un voyage par tag,
  en une seule requête ; c'est ce que consomme `OrchestrationPayloadBuilder`.
- **Tailles d'affichage** : chaque image reçoit, en plus de la vignette carrée
  `thumb`, deux conversions qui gardent son format — `medium` (900 px) et
  `large` (1800 px) — pour qu'un front n'envoie jamais l'original d'un
  smartphone. Le payload d'une image porte `url`, `thumb`, `medium`, `large`
  (l'original tant qu'une conversion n'est pas générée), ses `width` et
  `height` telles qu'elles s'affichent, sa légende (propriété `caption`) et
  `alt`. Les dimensions sont lues une fois sur la conversion `large` puis
  gardées dans les propriétés du média (`LibraryMedia::dimensions()`). Pour des
  images entrées avant ces conversions :
  `artisan media-library:regenerate --only=medium --only=large`.
- **Libellés** : un tag technique (`day:3f9c…`) s'affiche par le
  `LibraryTagLabeler` de l'application (« J2 · Arrivée à Lisbonne »).

### Ordre, en-tête et date de repli

- **Ordre d'un ensemble** : les images d'une journée se réordonnent par
  glisser-déposer dans `TagImagesPanel` (le `x-sortable` natif de Filament). La
  position est portée par le pivot du tag (colonne `taggables.sort`) : une même
  image peut être la première d'une journée et la troisième d'une autre. Tant
  que personne n'a réordonné, les images se suivent dans l'ordre de prise de vue ;
  une image ajoutée plus tard vient après celles qui ont été placées.
- **En-tête** : la première image d'un ensemble en est l'en-tête. Le panneau la
  met en avant, `LibraryImages::header()` la donne, et le payload la marque
  (`header: true`) dans les images d'un contenu.
- **Image de « une »** : un ensemble à part, d'une seule image (la couverture
  d'une journée). `TagImagesPanel` l'affiche en mode `single` (on remplace, on ne
  réordonne rien, l'image n'a ni numéro ni clé de référence) ; ses `coverTags`
  disent où lire la couverture, si bien que le panneau des photos marque la
  première tant qu'aucune n'est choisie, et ses `libraryTags` l'ensemble sur
  lequel s'ouvre la bibliothèque. C'est l'application qui pose l'action de la
  bibliothèque (voir `LibraryAction` ci-dessous).
- **Date de repli** : sans date de prise de vue dans l'EXIF, l'image prend la date
  de son fichier — pour un fichier envoyé par un navigateur, celle de l'envoi.
  Elle est marquée `date_source = file` (`exif` pour une vraie prise de vue,
  `manual` quand on l'a corrigée) et `LibraryMedia::hasReliableDate()` la
  distingue : un tagger ne doit pas s'y fier.

### Vidéos

Une vidéo entre dans la bibliothèque comme une image (`LibraryIngestor`), mais **le
serveur ne la retouche jamais** : ni conversion, ni ffmpeg. Ce qu'il en lit, il le
lit dans le fichier :

- MP4 et MOV : `Library\VideoMetadataReader` parse l'atome `moov` en PHP — `mvhd`
  (date de création et durée), `tkhd` (dimensions, rotation comprise), `udta/©xyz`
  (position GPS). La date est en UTC ; elle est ramenée au fuseau du voyage
  (`library.video_timezone`, variable `LIBRARY_VIDEO_TIMEZONE`, UTC par défaut) pour rester une heure « murale », comme
  celle d'une photo. Sans date, celle du fichier tient lieu (`date_source = file`).
- L'aperçu (première image), la durée et les dimensions des autres formats sont lus
  **par le navigateur** à l'envoi.
- La **coupe** est non destructive : `trim_start` / `trim_end` (secondes) sont des
  propriétés du média, que le payload traduit en fragment `#t=début,fin` de l'URL,
  doublé d'une garde JavaScript. Le fichier reste entier.

`LibraryMedia::kind()` / `isVideo()` disent ce qu'est un média, le payload porte
`type` (`image` ou `video`) et les images et les vidéos se numérotent à part. La
limite d'envoi est de 100 Mo (`config/media-library.php` et `config/livewire.php`
de l'application).

### Glisser-déposer et références

Les cartes de la bibliothèque et les vignettes de `TagImagesPanel` se glissent
(type `application/x-orchestrator-library-image`, données : le voyage et la liste des
médias). Elles se déposent :

- sur la case « + » de fin de `TagImagesPanel` : l'image rejoint l'ensemble
  (`attachMedia()`) ;
- dans un éditeur de texte muni de `Library\RichEditor\LibraryImagePlugin` : une
  **référence** s'y écrit, un nœud qui ne retient que les clés des médias et leur
  genre — jamais leur place. Le numéro (« (image 3) », « (vidéos 1, 2) ») est
  calculé à l'affichage, d'après l'ordre courant. Une référence dont le média a quitté
  l'ensemble est *orpheline* (rouge dans l'éditeur ; le front l'ignore).

Le survol d'une référence et de la vignette correspondante se répondent par
l'événement navigateur `LibraryImageEvent::HOVER`. Un dépôt dans le texte émet
`LibraryImageEvent::DROPPED` : c'est à la page d'y répondre (rattacher l'image à la
période, par exemple).

### Fenêtre d'édition

Un clic sur une carte ouvre une fenêtre large : un bandeau (nom, légende, texte
alternatif, date, position, tags, coupe d'une vidéo) et l'aperçu. Enregistrer sans
toucher à la date la laisse intacte (`date_source` inchangé) ; la modifier la marque
`manual`. La fenêtre permet aussi de supprimer le média, après confirmation.

`taken_at` est une heure murale : elle passe par l'attribut `LibraryMedia::takenAt()`
et non par la façade `Date`, dont un fuseau utilisateur (`Date::useCallable`) la
décalerait. Pour recalculer les dates depuis les fichiers (import, ancien décalage) :
`artisan orchestrator:realign-library-dates` (les dates saisies à la main sont respectées).

### Aide contextuelle

`TagImagesPanel` accepte un paramètre `help` : l'identifiant d'une page de la base de
connaissances (`voyage.images`). Son titre porte alors un « ? », un simple lien
`#modal-<identifiant>` que la base de connaissances ouvre en fenêtre ; sans elle, le
lien ne mène nulle part. Le paquet n'en dépend pas.

### Actions ajoutées à la bibliothèque

L'application crée ses propres actions en étendant `Library\LibraryAction` et les
déclare dans `filament-orchestrator.library.actions`. Elles rejoignent le menu de
la sélection et peuvent marquer d'une icône les images qu'elles concernent :

```php
class HeaderImageAction extends LibraryAction
{
    protected function setUp(): void
    {
        $this->name('header')->label('Image d’en-tête')->setIcon('heroicon-s-star')->single();
    }

    // Proposée seulement depuis une journée, pas dans la bibliothèque générale.
    public function appliesTo(LibraryContext $context): bool { return $context->hasFocus(); }

    // Cette image porte-t-elle la marque ? Son icône s'affiche alors sur sa carte.
    public function marks(LibraryMedia $media, LibraryContext $context): bool { /* … */ }

    public function handle(Collection $media, LibraryContext $context): ?string { /* … */ }
}
```

Les tags d'une bibliothèque sont rangés sous un type propre à l'orchestration :
deux voyages ne partagent jamais un tag. La configuration se trouve sous la clé
`library` ; comme la fusion de config est superficielle, une application qui la
publie doit en reprendre toutes les clés.

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

### Dessiner soi-même un contenu

À chaque ouverture d'un contenu — clic sur un point, action `content.open` ou
`content.next`, démarrage du parcours — le moteur émet sur `window` l'événement
**annulable** `filament-orchestrator:content-opened`
(`detail: { orchestrationId, scope, node, instance }`). Sans écouteur, il écrit
son panneau par défaut, comme avant. Un front qui a sa propre mise en page
l'annule, et le moteur n'écrit alors rien :

```js
window.addEventListener('filament-orchestrator:content-opened', (event) => {
    event.preventDefault()
    afficher(event.detail.node.data) // title, body, images, buttons
})
```

Le moteur retient le contenu actif et émet `content.initialized` dans les deux
cas, et il n'exige plus le panneau par défaut : une page qui n'inclut que la
carte peut ouvrir des contenus. `instance.openContent(node)` et
`instance.mapCommand(...)` sont l'API publique pour passer d'une étape à l'autre
depuis un bouton de la page.

### Ajouter une action

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
