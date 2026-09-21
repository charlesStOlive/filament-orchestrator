<?php

namespace CharlesStOlive\FilamentOrchestrator\Library\Filament;

use Closure;
use CharlesStOlive\FilamentOrchestrator\Library\IngestContext;
use CharlesStOlive\FilamentOrchestrator\Library\LibraryIngestor;
use CharlesStOlive\FilamentOrchestrator\Library\TagLabels;
use CharlesStOlive\FilamentOrchestrator\Models\LibraryMedia;
use CharlesStOlive\FilamentOrchestrator\Models\Orchestration;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
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
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Charger dans la bibliothèque')
            ->schema(fn (): array => [
                // Les fichiers ne sont pas stockés par le champ : l'ingestor
                // les range lui-même, une seule fois, dans la bibliothèque.
                FileUpload::make('files')
                    ->label('Fichiers')
                    ->multiple()
                    ->storeFiles(false)
                    ->acceptedFileTypes([...LibraryMedia::IMAGE_TYPES, ...LibraryMedia::VIDEO_TYPES])
                    ->maxFiles(50)
                    ->maxSize(self::MAX_VIDEO_SIZE)
                    ->required()
                    ->helperText($this->destinationHint().' Images : 20 Mo au plus. Vidéos (MP4, WebM ou MOV) : 100 Mo au plus.'),
            ])
            ->action(function (array $data): void {
                $orchestration = $this->getRecord();

                abort_unless($orchestration instanceof Orchestration, 404);

                $this->ingest($orchestration, (array) ($data['files'] ?? []));
            });
    }

    /** @param array<int, mixed> $files */
    private function ingest(Orchestration $orchestration, array $files): void
    {
        $ingestor = app(LibraryIngestor::class);
        $context = new IngestContext(tags: $this->getTags(), source: $this->evaluate($this->source));
        $added = 0;
        $failed = [];

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            // Une image plus lourde que la limite des images (celle des vidéos est plus large) : refusée, sans perdre les autres.
            if (! str_starts_with((string) $file->getMimeType(), 'video/') && $file->getSize() > self::MAX_IMAGE_SIZE * 1024) {
                $failed[] = $file->getClientOriginalName().' (image de plus de '.(self::MAX_IMAGE_SIZE / 1024).' Mo)';

                continue;
            }

            try {
                $ingestor->ingest($orchestration, $file, $context);
                $added++;
            } catch (Throwable $exception) {
                // Une image illisible ne doit pas faire perdre les suivantes.
                report($exception);
                $failed[] = $file->getClientOriginalName();
            }
        }

        if ($added > 0) {
            Notification::make()->success()->title($added.' fichier(s) ajouté(s) à la bibliothèque')->send();
            $this->getLivewire()?->dispatch(self::UPDATED_EVENT, orchestrationId: $orchestration->getKey());
        }

        if ($failed !== []) {
            Notification::make()->warning()
                ->title(count($failed).' fichier(s) n’ont pas pu être ajoutés')
                ->body(implode(', ', $failed))
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
