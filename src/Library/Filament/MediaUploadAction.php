<?php

namespace CharlesStOlive\FilamentOrchestrator\Library\Filament;

use Closure;
use CharlesStOlive\FilamentOrchestrator\Library\IngestContext;
use CharlesStOlive\FilamentOrchestrator\Library\LibraryDuplicates;
use CharlesStOlive\FilamentOrchestrator\Library\LibraryIngestor;
use CharlesStOlive\FilamentOrchestrator\Library\MetadataExtractor;
use CharlesStOlive\FilamentOrchestrator\Library\TagLabels;
use CharlesStOlive\FilamentOrchestrator\Models\LibraryMedia;
use CharlesStOlive\FilamentOrchestrator\Models\Orchestration;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Illuminate\Http\UploadedFile;
use Throwable;

/**
 * Charge des images et des vidéos dans la bibliothèque d'une orchestration.
 *
 * C'est l'uploader, distinct de la gestion : il n'affiche rien de la
 * bibliothèque, il y verse. Les tags qu'on lui donne sont posés sur chaque
 * image dès son arrivée — c'est ainsi qu'un envoi lancé depuis une journée
 * rattache ses photos à cette journée.
 *
 *     MediaUploadAction::make()->record($voyage)->tags(['day:3f9c…'])
 *
 * Une fois l'envoi terminé, l'événement Livewire `orchestrator-library-updated`
 * est émis : les composants qui affichent la bibliothèque se rafraîchissent.
 */
class MediaUploadAction extends Action
{
    public const UPDATED_EVENT = 'orchestrator-library-updated';

    /** Poids maximal d'une image, en Ko. Les vidéos vont jusqu'à `MAX_VIDEO_SIZE`. */
    public const MAX_IMAGE_SIZE = 20480;

    public const MAX_VIDEO_SIZE = 102400;

    /** @var array<int, string>|Closure */
    protected array|Closure $tags = [];

    protected string|Closure|null $source = null;

    public static function getDefaultName(): ?string
    {
        return 'mediaUpload';
    }

    /** @param  array<int, string>|Closure  $tags  Posés sur chaque image de l'envoi. */
    public function tags(array|Closure $tags): static
    {
        $this->tags = $tags;

        return $this;
    }

    /** D'où vient l'envoi, gardé dans les propriétés de l'image (« journée », « bibliothèque »…). */
    public function source(string|Closure|null $source): static
    {
        $this->source = $source;

        return $this;
    }

    /** @return array<int, string> */
    public function getTags(): array
    {
        return array_values((array) $this->evaluate($this->tags));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Charger des images')
            ->icon('heroicon-o-arrow-up-tray')
            ->modalHeading('Charger des images et des vidéos')
            ->modalWidth(Width::FourExtraLarge)
            ->modalSubmitActionLabel('Charger dans la bibliothèque')
            ->schema(fn (): array => [
                Tabs::make('source')->vertical()->tabs([
                    // Les fichiers ne sont pas stockés par le champ : l'ingestor
                    // les range lui-même, une seule fois, dans la bibliothèque.
                    Tab::make('upload')
                        ->label('Importer des fichiers')
                        ->icon('heroicon-o-arrow-up-tray')
                        ->schema([
                            FileUpload::make('files')
                                ->label('Fichiers')
                                ->multiple()
                                // Des vignettes carrées, 2 ou 3 par ligne, plutôt qu'une pleine largeur par fichier.
                                ->panelLayout('grid')
                                ->storeFiles(false)
                                ->acceptedFileTypes([...LibraryMedia::IMAGE_TYPES, ...LibraryMedia::VIDEO_TYPES])
                                ->maxFiles(50)
                                ->maxSize(self::MAX_VIDEO_SIZE)
                                ->helperText($this->destinationHint().' Images : 20 Mo au plus. Vidéos (MP4, WebM ou MOV) : 100 Mo au plus.'),
                        ]),
                    Tab::make('external')
                        ->label('Image externe')
                        ->icon('heroicon-o-cloud')
                        ->schema([
                            TextInput::make('external_url')
                                ->label('URL de l’image')
                                ->url()
                                ->maxLength(2048)
                                ->helperText('L’image est copiée dans la bibliothèque depuis cette adresse.'),
                            TextInput::make('copyright')
                                ->label('Copyright')
                                ->maxLength(255)
                                ->helperText('Le nom du photographe ou de la source, à créditer.'),
                        ]),
                    Tab::make('youtube')
                        ->label('YouTube')
                        ->icon('heroicon-o-play-circle')
                        ->schema([
                            TextInput::make('youtube_url')
                                ->label('Lien de la vidéo')
                                ->maxLength(2048)
                                ->helperText('Collez n’importe quel lien de partage YouTube : seul l’identifiant de la vidéo est gardé.'),
                        ]),
                ]),
            ])
            ->action(function (array $data): void {
                $orchestration = $this->getRecord();

                abort_unless($orchestration instanceof Orchestration, 404);

                if (filled($data['files'] ?? null)) {
                    $this->ingest($orchestration, (array) $data['files']);

                    return;
                }

                if (filled($data['youtube_url'] ?? null)) {
                    $this->ingestYoutube($orchestration, (string) $data['youtube_url']);

                    return;
                }

                if (filled($data['external_url'] ?? null)) {
                    $this->ingestExternalImage($orchestration, (string) $data['external_url'], $data['copyright'] ?? null);

                    return;
                }

                Notification::make()->warning()
                    ->title('Rien à charger')
                    ->body('Choisissez des fichiers, une URL d’image externe ou un lien YouTube.')
                    ->send();
            });
    }

    /**
     * Charge les fichiers, sauf ceux qui sont déjà dans la bibliothèque (voir LibraryDuplicates) — ou deux fois dans cet
     * envoi : ceux-là sont mis de côté, et une seconde fenêtre (MediaDuplicatesAction) demande lesquels charger quand
     * même. Elle remplace celle-ci, si le composant qui l'héberge la déclare ; sinon ils ne sont pas chargés, et une
     * notification le dit.
     *
     * @param array<int, mixed> $files
     */
    private function ingest(Orchestration $orchestration, array $files): void
    {
        $ingestor = app(LibraryIngestor::class);
        $extractor = app(MetadataExtractor::class);
        $duplicates = app(LibraryDuplicates::class);
        $context = new IngestContext(tags: $this->getTags(), source: $this->evaluate($this->source));
        $added = [];
        $failed = [];
        $setAside = [];
        $seen = [];

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            // Une image plus lourde que la limite des images (celle des vidéos est plus large) : refusée, sans perdre les autres.
            if (! str_starts_with((string) $file->getMimeType(), 'video/') && $file->getSize() > self::MAX_IMAGE_SIZE * 1024) {
                $failed[] = $file->getClientOriginalName().' (image de plus de '.(self::MAX_IMAGE_SIZE / 1024).' Mo)';

                continue;
            }

            $name = $file->getClientOriginalName();
            $metadata = $extractor->extract((string) $file->getRealPath());
            $key = $duplicates->key($name, $metadata, (int) $file->getSize());
            $existing = $duplicates->find($orchestration, $name, $metadata, (int) $file->getSize());

            if ($existing !== null || isset($seen[$key])) {
                $setAside[] = [
                    'path' => (string) $file->getRealPath(),
                    'name' => $name,
                    'mime' => (string) $file->getMimeType(),
                    'existing' => $existing?->getKey() ?? $seen[$key],
                ];

                continue;
            }

            try {
                $media = $ingestor->ingest($orchestration, $file, $context);
                $added[] = $media;
                $seen[$key] = $media->getKey();
            } catch (Throwable $exception) {
                // Une image illisible ne doit pas faire perdre les suivantes.
                report($exception);
                $failed[] = $name;
            }
        }

        self::notifyAdded($orchestration, $added, $this->getLivewire());

        if ($failed !== []) {
            Notification::make()->warning()
                ->title(count($failed).' fichier(s) n’ont pas pu être ajoutés')
                ->body(implode(', ', $failed))
                ->send();
        }

        if ($setAside === []) {
            return;
        }

        $livewire = $this->getLivewire();

        if ($livewire !== null && method_exists($livewire, MediaDuplicatesAction::NAME.'Action') && method_exists($livewire, 'replaceMountedAction')) {
            $livewire->replaceMountedAction(MediaDuplicatesAction::NAME, MediaDuplicatesAction::argumentsFor(
                $orchestration,
                $context,
                $setAside,
            ));

            return;
        }

        Notification::make()->warning()
            ->title(count($setAside).' fichier(s) déjà dans la bibliothèque, non chargés')
            ->body(implode(', ', array_column($setAside, 'name')))
            ->send();
    }

    /**
     * Dit combien de fichiers ont rejoint la bibliothèque — et, quand leurs conversions passent par la file d'attente,
     * que les images sont en cours d'optimisation — puis prévient les composants qui l'affichent.
     *
     * @param  array<int, LibraryMedia>  $added
     */
    public static function notifyAdded(Orchestration $orchestration, array $added, mixed $livewire, ?string $title = null): void
    {
        if ($added === []) {
            return;
        }

        $optimizing = count(array_filter($added, fn (LibraryMedia $media): bool => $media->isOptimizing()));

        Notification::make()->success()
            ->title($title ?? count($added).' fichier(s) ajouté(s) à la bibliothèque')
            ->body($optimizing > 0
                ? ($optimizing > 1 ? "{$optimizing} images sont" : 'Une image est').' en cours d’optimisation : vignettes et tailles d’affichage arrivent dans un instant.'
                : null)
            ->send();

        $livewire?->dispatch(self::UPDATED_EVENT, orchestrationId: $orchestration->getKey());
    }

    private function ingestExternalImage(Orchestration $orchestration, string $url, ?string $copyright): void
    {
        $context = new IngestContext(tags: $this->getTags(), source: $this->evaluate($this->source));

        try {
            $media = app(LibraryIngestor::class)->ingestExternalImage($orchestration, $url, $copyright, $context);
            self::notifyAdded($orchestration, [$media], $this->getLivewire(), 'Image ajoutée à la bibliothèque');
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->danger()
                ->title('L’image n’a pas pu être ajoutée')
                ->body('Vérifiez que l’adresse pointe bien vers une image accessible.')
                ->send();
        }
    }

    private function ingestYoutube(Orchestration $orchestration, string $urlOrId): void
    {
        $context = new IngestContext(tags: $this->getTags(), source: $this->evaluate($this->source));

        try {
            $media = app(LibraryIngestor::class)->ingestYoutube($orchestration, $urlOrId, $context);
            self::notifyAdded($orchestration, [$media], $this->getLivewire(), 'Vidéo YouTube ajoutée à la bibliothèque');
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->danger()
                ->title('La vidéo n’a pas pu être ajoutée')
                ->body('Vérifiez que le lien pointe bien vers une vidéo YouTube.')
                ->send();
        }
    }

    private function destinationHint(): ?string
    {
        $orchestration = $this->getRecord();
        $tags = $this->getTags();

        if (! $orchestration instanceof Orchestration || $tags === []) {
            return 'Les images rejoignent la bibliothèque, sans tag.';
        }

        $labels = app(TagLabels::class);

        return 'Ces images seront rattachées à : '
            .implode(', ', array_map(fn (string $tag): string => $labels->label($tag, $orchestration), $tags)).'.';
    }
}
