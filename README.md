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

Un **point** peut de même proposer une image à son marqueur :
`NodeDeclaration::markerImage(['cover:…'], ['day:…'])` — la première image du premier jeu de tags qui en a une
(l'image de une, sinon la première photo). `Integrations\MapScenes\ScenePoints` la lit à l'affichage et la passe à
`MapPayloadBuilder::point($point, image:)` de filament-map, seulement si le type du point montre une image
(`GeoPointType::acceptsImage()`) : un type qui n'est qu'une forme l'ignore, sans erreur et sans requête.

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
- **Doublons** : au clic sur « Charger », un fichier déjà dans la bibliothèque —
  même nom d'origine (`original_name`, gardé à l'envoi ; pour un média plus
  ancien, son `file_name`), et même date de prise de vue lue dans le fichier
  ou même poids — est mis de côté, comme un fichier présent deux fois dans
  l'envoi (`Library\LibraryDuplicates`). Les autres sont chargés, puis
  `MediaDuplicatesAction` remplace la fenêtre de l'envoi : on coche ceux à
  charger quand même. Le composant qui héberge l'envoi la déclare par une
  méthode `mediaDuplicatesAction()` (c'est le cas de `MediaLibraryTable`) ;
  sans elle, les doublons ne sont pas chargés et une notification les nomme.
  Ses arguments (fichiers temporaires, tags, voyage) sont chiffrés.
- **Gérer** : `MediaLibraryAction` ouvre `MediaLibraryTable`, une grille de
  cartes avec filtres (date, tags, GPS, autour d'un point), groupements (date,
  zone), actions groupées de tag, et trois tailles de vignettes S / M / L (S : petits carrés à icônes ; le choix est gardé en session). Elle s'ajoute comme n'importe quelle
  action : `MediaLibraryAction::make()->record($this->record)`. Un clic sur une
  carte la coche (la case, en haut à gauche, a une zone à elle : un clic à côté
  coche aussi) ; le crayon ouvre sa fiche. Un clic n'est qu'un clic : le pointeur
  qui a bougé depuis l'appui, c'est un glisser — saisir une carte cochée ne la
  décoche pas. Aujourd'hui, seul l'éditeur de voyage de l'application s'en sert ;
  rien dans le plugin ne le suppose.
- **Choisir des fichiers** : `MediaLibraryAction::make()->pickFor($this->getId(), many: true)`
  ouvre la bibliothèque pour choisir, au profit de qui l'a ouverte. Un seul
  fichier attendu, un clic sur une carte le propose (confirmation `pickOne`,
  avec l'image) ; plusieurs, un clic coche,
  et « Insérer la sélection » les envoie. Rien ne s'y glisse. Les fichiers sont
  annoncés par l'événement `MediaLibraryTable::PICKED_EVENT` (`picker`,
  `media`) : à qui les attend de les prendre et de fermer la fenêtre (c'est ce
  que fait `TagImagesPanel` quand sa bibliothèque s'ouvre en fenêtre).
- **Version légère** : `TagImagesPanel` est un composant Livewire à glisser
  dans l'écran d'un contenu (une journée, une introduction) : les images qui
  portent ses tags, un envoi qui les étiquette d'emblée, et un accès à la
  bibliothèque complète — ouverte « au service » de ces tags avec
  `MediaLibraryAction::make()->focusTags([...])` — pour en rattacher d'autres
  par lot ou n'afficher que celles de la journée.
- **Choisir une sorte d'images** : `MediaLibraryAction::make()->filterTags(['croquis'])`
  ouvre la bibliothèque déjà filtrée sur ces tags (le filtre « Tags », qu'on
  retire comme un autre). Rien n'est étiqueté : c'est un point de départ, pas
  un contexte. Même option sur `MediaLibraryTable::component(…, filterTags: […])`,
  et sur `TagImagesPanel` (`libraryFilterTags`, avec `libraryFocused: false` pour
  un ensemble d'une image qu'une action de la bibliothèque désigne elle-même :
  le « + » ouvre alors une bibliothèque filtrée, au service d'aucun tag). Le
  volet latéral reçoit aussi le filtre, par `filterTags` dans son contexte.
  De même, `filterDates` (`['from' => 'Y-m-d', 'until' => 'Y-m-d']`, dans le
  contexte du volet ou sur `MediaLibraryTable::component()`) pose le filtre
  « Date de prise de vue ». Un filtre posé par le contexte s'en va quand le
  contexte suivant ne le demande plus — sauf s'il a été retouché à la main.
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
- **File d'attente** : avec `library.queue_conversions` à `true`, les trois
  conversions passent par la file (un worker doit tourner) et l'envoi rend la
  main tout de suite. En attendant, l'image s'affiche par son original et se dit
  « en cours d'optimisation » (`LibraryMedia::isOptimizing()`, scope
  `optimizing()`) : pastille sur la carte et la tuile, phrase dans la
  notification de l'envoi, grille et panneau qui se redessinent toutes les 4 s
  jusqu'à ce qu'elles soient là. Au-delà de 30 minutes
  (`LibraryMedia::OPTIMIZING_MINUTES`), une conversion manquante est tenue pour
  échouée : l'image ne se dit plus en cours. La vignette refaite par un
  cadrage (`setFocus()`), elle, reste immédiate.
- **Libellés** : un tag technique (`day:3f9c…`) s'affiche par le
  `LibraryTagLabeler` de l'application (« J2 · Arrivée à Lisbonne »).
- **Barre « En cours : … »** : ouverte au service de tags (`focusTags`), la
  bibliothèque porte dans son bandeau des raccourcis vers les filtres communs —
  il n'y a plus de filtre propre au contexte dans la fenêtre de filtres :
  - « Seulement ses fichiers » (`toggleFocusTags()`) **ajoute** ces tags au
    filtre « Tags », sans toucher à ceux déjà cochés (ouverte avec
    `filterTags(['croquis'])`, on voit alors les croquis de la journée) ; le
    filtre « Tags » garde les images qui ont **tous** les tags cochés ;
  - « Pris du … au … » (`toggleFocusDates()`) pose le filtre « Date de prise de
    vue » sur les dates des tags. Elles viennent d'un
    `Library\Contracts\LibraryTagDates` de l'application
    (`filament-orchestrator.library.tag_dates`), qui dit quelles dates couvre un
    tag (`['from' => 'Y-m-d', 'until' => 'Y-m-d']`, ou null ; de la première à
    la dernière s'il y a plusieurs tags). Sans fournisseur, ou pour un tag
    qu'aucun ne reconnaît, pas de bouton.

  Un bouton est actif tant que son filtre contient ce qu'il a posé ; un second
  clic ne retire que cela. Dans le volet, un bouton actif suit le contexte
  suivant (le tag et les dates de la nouvelle journée) ; un filtre retouché à la
  main n'est plus celui du bouton et reste.

### Fichiers qu'on ne peut pas supprimer

Une application peut retenir un fichier de la bibliothèque — une image que
montre une version publiée, par exemple — avec une
`Library\Contracts\LibraryDeletionGuard`
(`filament-orchestrator.library.deletion_guards`) : `reason($media)` rend
pourquoi il doit rester, en une phrase, ou null. La bibliothèque le dit au lieu
de supprimer (la poubelle n'a plus de bouton « Supprimer », la suppression
groupée garde ces fichiers, un lien YouTube ne remplace pas une vidéo retenue),
et `LibraryMedia` refuse la suppression d'où qu'elle vienne — y compris la
suppression de l'orchestration, qui emporte ses fichiers — en levant
`Library\MediaInUse`. `$media->keptReason()` interroge les gardes.

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

### Cadrage d'une image

- **Neuf positions** (`LibraryMedia::FOCUSES` : `top-left`, `top`, … `bottom-right`,
  `center` par défaut) disent la partie à garder quand un affichage recadre
  l'image. Elles sont gardées sur l'image (propriété `focus`), non destructives :
  le fichier reste entier. Une vidéo n'en a pas.
- **Dans `TagImagesPanel`** (grille comme `single`), un clic sur une vignette
  (sans la glisser) ouvre une grille de 3 × 3 : la même image dans chaque case
  carrée, recadrée de ce côté et agrandie de 20 % vers lui (pour que la différence
  se voie même sur une image presque carrée), une flèche par-dessus ; « Appliquer » l'enregistre
  (`LibraryMedia::setFocus()`). Une petite flèche marque ensuite la vignette.
- **Côté navigateur**, le payload porte `focus` (`{x, y}` en %) et
  `objectPosition` (« 50% 0% ») : à l'affichage d'appliquer `object-position`.
- **La vignette carrée** (`thumb`) suit le cadrage : réduite par son petit côté,
  l'image est découpée de ce côté (`LibraryMedia::thumbCrop()`), et `setFocus()`
  refait la vignette. Son adresse porte le cadrage (`?focus=top`), pour qu'aucun
  navigateur ne garde l'ancienne en cache.

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

Le glisser montre une petite étiquette (`resources/js/library-drag.js`, « Image 3 » ou
le nom du fichier) plutôt que la carte, et l'éditeur un trait épais au point de dépôt.
Resté un moment au milieu d'un mot, le glisser l'englobe : la référence prend ce mot
(`data-label`), que le front peut écrire à la place de « (image 3) ». Un clic sur la
référence ouvre une petite fenêtre : un titre (`data-title`), le mot, la suppression
(le mot englobé reste dans le texte).

Le survol d'une référence et de la vignette correspondante se répondent par
l'événement navigateur `LibraryImageEvent::HOVER`. Un dépôt dans le texte émet
`LibraryImageEvent::DROPPED` : c'est à la page d'y répondre (rattacher l'image à la
période, par exemple).

### Fenêtre d'édition

Le crayon d'une carte ouvre une fenêtre large : un bandeau (nom, légende, texte
alternatif, date, position, tags, coupe d'une vidéo) et l'aperçu. Enregistrer sans
toucher à la date la laisse intacte (`date_source` inchangé) ; la modifier la marque
`manual`. La fenêtre permet aussi de supprimer le média, après confirmation.

`taken_at` est une heure murale : elle passe par l'attribut `LibraryMedia::takenAt()`
et non par la façade `Date`, dont un fuseau utilisateur (`Date::useCallable`) la
décalerait. Pour recalculer les dates depuis les fichiers (import, ancien décalage) :
`artisan orchestrator:realign-library-dates` (les dates saisies à la main sont respectées).

### Aide contextuelle

`TagImagesPanel` accepte un paramètre `help` : l'identifiant d'une page de la base de
connaissances (`voyage.images`). Son titre est alors suivi d'un « ? » (couleur `info`,
`helpLabel` en infobulle), un simple lien `#modal-<identifiant>` que la base de
connaissances ouvre en fenêtre ; sans elle, le lien ne mène nulle part. Le paquet n'en
dépend pas. `helpFullTitle: true` affiche plutôt `helpLabel` en toutes lettres, au bout
de la ligne.

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

Une action qui a besoin de **réglages** avant de s'exécuter déclare un formulaire
(`schema()`) : la bibliothèque l'ouvre en modale avec les images cochées sous la
main, puis passe ce qui a été saisi à `submit()` (au lieu de `handle()`). Ce qui
rend la sélection irrecevable — `single()`, `accepts()`, et `refuses()` pour la
sélection entière (trop peu d'images…) — est dit **avant** d'ouvrir la modale.
Le paquet n'y met rien de spécifique : une application s'en sert, par exemple,
pour demander un traitement à une IA sans que ce paquet en dépende.

```php
class CaptionAction extends LibraryAction
{
    protected function setUp(): void
    {
        $this->name('caption')->label('Légender')->modalSubmitLabel('Appliquer');
    }

    public function refuses(Collection $media): ?string
    {
        return $media->count() > 20 ? 'Cochez 20 images au plus' : null;
    }

    public function schema(Collection $media, LibraryContext $context): ?array
    {
        return [TextInput::make('caption')->label('Légende')->required()];
    }

    public function submit(Collection $media, LibraryContext $context, array $data): ?string { /* … */ }
}
```

#### Boutons directs (raccourcis), désactivés par défaut

Par défaut, **tout ce qui porte sur les images cochées est dans le menu
« Sélection »** : la barre d'outils ne montre que l'envoi, ce menu et les tailles.
Pour un geste qu'on fait sans cesse, on peut en faire un **bouton toujours
visible** de la barre d'outils (il prévient, comme le menu, si rien n'est coché) :

- une action de l'application : `->shortcut()` dans son `setUp()`, avec
  `->shortLabel('Image de une')` si le libellé complet est trop long (il
  devient l'infobulle du bouton) ;
- « Ajouter à : période » (bibliothèque ouverte au service d'une période) :
  `'add_to_focus_shortcut' => true` sous la clé `library` de la config — il
  devient un bouton « Ajouter », la période en infobulle. « Retirer de : … »
  reste dans le menu.

```php
protected function setUp(): void
{
    $this->name('header')
        ->label('Définir comme image de une')
        ->shortLabel('Image de une')
        ->single()
        ->shortcut();
}
```

Chaque bouton prend de la place, surtout dans un volet étroit : à réserver aux
un ou deux gestes vraiment courants.

Les tags d'une bibliothèque sont rangés sous un type propre à l'orchestration :
deux voyages ne partagent jamais un tag. La configuration se trouve sous la clé
`library` ; comme la fusion de config est superficielle, une application qui la
publie doit en reprendre toutes les clés.

### Droits de la bibliothèque (`LibraryPermissions`)

Charger, modifier, taguer, supprimer des images, et chaque action déclarée dans
`library.actions`, consultent l'ability Gate
`{Resource de l'automatisation}.library.{geste ou nom de l'action}` quand
l'application la définit ; sinon, ils restent ouverts à qui ouvre la bibliothèque.
Le package ne dépend d'aucun gestionnaire de permissions : `AutomationResource`
déclare ces actions au format de filament-permission-manager
(`$permissionFamilies = ['library' => 'Bibliothèque']`, `permissionActions()`),
qui en fait la famille « Bibliothèque » de l'écran des rôles
(`{liste}.library.upload`, `….library.header`… et `{liste}.library.*` pour tout,
y compris les actions à venir). Une action peut ranger son droit sous un sous-titre de la
famille (`->permissionGroup('Intelligence artificielle')`) : son nom ne change pas.

Sans le droit de modifier, la fiche d'une image s'ouvre en lecture (un œil sur la
carte plutôt qu'un crayon) ; sans celui de supprimer, la poubelle disparaît. Ajouter
des images à une journée, les ordonner ou les retirer, c'est modifier
l'orchestration : son droit de modification suffit.

Un `TagImagesPanel` qui fait la même chose qu'une action de la bibliothèque (le
croquis d'un carnet, son image de une) le dit par `permission: 'sketch'` : sans
ce droit, il montre ses images sans « + », sans dépôt, sans retrait ni
réordonnancement — le panneau ne contourne pas l'action.

Une automatisation qui ajoute ses propres familles redéclare `$permissionFamilies`
avec `library` et fusionne `parent::permissionActions()`.

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

Le lecteur montre l'orchestration telle qu'elle est. Tout ce qu'il affiche —
le payload du moteur, et pour chaque scène sa scène, ses hotpoints et ses
réglages — sort de `Services\PlayerStateBuilder::build()`, en données
seulement. Une application qui publie des versions garde ce tableau, et le rend
au lecteur sous une version :

```blade
<livewire:filament-orchestrator-player :orchestration="$orchestration" :version="$publication->id" />
```

Il le demande alors à la classe de `filament-orchestrator.player.state_resolver`
(`Contracts\PlayerStateResolver::state($orchestration, $version)`), et répond
404 si elle ne le connaît pas. Les contenus et les hotpoints peuvent changer ou
disparaître : la version montrée n'en dépend plus. La scène elle-même (fond de
carte, couches) reste lue en direct.

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
