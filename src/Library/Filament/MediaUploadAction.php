<?php

namespace CharlesStOlive\FilamentOrchestrator\Library\Filament;

use Closure;
use CharlesStOlive\FilamentOrchestrator\Library\IngestContext;
use CharlesStOlive\FilamentOrchestrator\Library\LibraryIngestor;
use CharlesStOlive\FilamentOrchestrator\Library\TagLabels;
use CharlesStOlive\FilamentOrchestrator\Models\Orchestration;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Illuminate\Http\UploadedFile;
use Throwable;

/**
 * Envoie des images dans la bibliothèque d'une orchestration.
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
            ->label('Ajouter des images')
            ->icon('heroicon-o-arrow-up-tray')
            ->modalHeading('Ajouter des images')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Ajouter à la bibliothèque')
            ->schema(fn (): array => [
                // Les fichiers ne sont pas stockés par le champ : l'ingestor
                // les range lui-même, une seule fois, dans la bibliothèque.
                FileUpload::make('files')
                    ->label('Images')
                    ->multiple()
                    ->storeFiles(false)
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
                    ->maxFiles(50)
                    ->maxSize(20480)
                    ->required()
                    ->helperText($this->destinationHint()),
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
            Notification::make()->success()->title($added.' image(s) ajoutée(s) à la bibliothèque')->send();
            $this->getLivewire()?->dispatch(self::UPDATED_EVENT, orchestrationId: $orchestration->getKey());
        }

        if ($failed !== []) {
            Notification::make()->warning()
                ->title(count($failed).' image(s) n’ont pas pu être ajoutées')
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
