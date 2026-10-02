<?php

namespace CharlesStOlive\FilamentOrchestrator\Library\Filament;

use CharlesStOlive\FilamentOrchestrator\Library\IngestContext;
use CharlesStOlive\FilamentOrchestrator\Library\LibraryIngestor;
use CharlesStOlive\FilamentOrchestrator\Models\LibraryMedia;
use CharlesStOlive\FilamentOrchestrator\Models\Orchestration;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * La seconde fenêtre d'un envoi : les fichiers qui semblaient déjà dans la bibliothèque (voir LibraryDuplicates) ont
 * été mis de côté pendant que les autres étaient chargés. On coche ceux à charger quand même ; les autres sont oubliés.
 *
 * MediaUploadAction l'ouvre à la place de sa propre fenêtre, sur le composant qui l'héberge : celui-ci la déclare
 * par une méthode `mediaDuplicatesAction()` (comme MediaLibraryTable).
 *
 *     public function mediaDuplicatesAction(): Action
 *     {
 *         return MediaDuplicatesAction::make();
 *     }
 *
 * Ce qu'elle reçoit (l'orchestration, les tags de l'envoi, les fichiers mis de côté) est chiffré : les arguments d'une
 * action passent par le navigateur, qui ne doit pouvoir désigner ni un autre fichier du serveur ni un autre voyage.
 */
class MediaDuplicatesAction extends Action
{
    public const NAME = 'mediaDuplicates';

    public static function getDefaultName(): ?string
    {
        return self::NAME;
    }

    /**
     * Les arguments de la fenêtre.
     *
     * @param  array<int, array{path: string, name: string, mime: string, existing: int|null}>  $files
     * @return array{payload: string}
     */
    public static function argumentsFor(Orchestration $orchestration, IngestContext $context, array $files): array
    {
        return ['payload' => encrypt([
            'orchestration' => $orchestration->getKey(),
            'tags' => $context->tags,
            'source' => $context->source,
            'files' => array_values($files),
        ])];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->modalHeading(fn (array $arguments): string => count($this->payload($arguments)['files']) > 1
                ? 'Ces fichiers sont peut-être déjà dans la bibliothèque'
                : 'Ce fichier est peut-être déjà dans la bibliothèque')
            ->modalDescription('Les autres fichiers ont été chargés. Ceux-ci portent le même nom qu’une image déjà chargée, '
                .'avec la même date de prise de vue ou le même poids : cochez ceux à charger quand même.')
            ->modalIcon('heroicon-o-document-duplicate')
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel('Charger les fichiers cochés')
            ->modalCancelActionLabel('Ne pas les charger')
            ->schema(fn (array $arguments): array => [
                CheckboxList::make('files')
                    ->hiddenLabel()
                    ->options($this->options($arguments))
                    ->descriptions($this->descriptions($arguments))
                    ->allowHtml()
                    ->bulkToggleable()
                    ->default([]),
            ])
            ->action(function (array $data, array $arguments): void {
                $payload = $this->payload($arguments);
                $orchestration = Orchestration::query()->find($payload['orchestration']);

                abort_unless($orchestration instanceof Orchestration, 404);

                $context = new IngestContext(tags: $payload['tags'], source: $payload['source']);
                $ingestor = app(LibraryIngestor::class);
                $added = [];

                foreach (array_map('intval', (array) ($data['files'] ?? [])) as $index) {
                    $file = $payload['files'][$index] ?? null;

                    if ($file === null || ! is_file($file['path'])) {
                        continue;
                    }

                    try {
                        $added[] = $ingestor->ingest($orchestration, new UploadedFile($file['path'], $file['name'], $file['mime'], null, true), $context);
                    } catch (Throwable $exception) {
                        report($exception);
                    }
                }

                MediaUploadAction::notifyAdded($orchestration, $added, $this->getLivewire());
            });
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{orchestration: int, tags: array<int, string>, source: string|null, files: array<int, array{path: string, name: string, mime: string, existing: int|null}>}
     */
    private function payload(array $arguments): array
    {
        try {
            $payload = decrypt((string) ($arguments['payload'] ?? ''));
        } catch (DecryptException) {
            $payload = null;
        }

        return is_array($payload) ? $payload : ['orchestration' => 0, 'tags' => [], 'source' => null, 'files' => []];
    }

    /**
     * Chaque fichier mis de côté, avec la vignette de l'image qui lui ressemble.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<int, HtmlString>
     */
    private function options(array $arguments): array
    {
        $existing = $this->existing($arguments);

        return collect($this->payload($arguments)['files'])
            ->map(function (array $file) use ($existing): HtmlString {
                $media = $existing->get($file['existing']);
                $thumb = $media !== null && ! $media->isVideo()
                    ? '<img src="'.e($media->thumbUrl()).'" alt="" class="h-10 w-10 shrink-0 rounded object-cover" />'
                    : '';

                return new HtmlString('<span class="inline-flex items-center gap-2">'.$thumb.'<span>'.e($file['name']).'</span></span>');
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<int, string>
     */
    private function descriptions(array $arguments): array
    {
        $existing = $this->existing($arguments);

        return collect($this->payload($arguments)['files'])
            ->map(function (array $file) use ($existing): string {
                $media = $existing->get($file['existing']);

                if ($media === null) {
                    return 'Déjà dans la bibliothèque.';
                }

                return 'Déjà chargé le '.$media->created_at?->format('d/m/Y').' sous le nom « '.$media->name.' »'
                    .($media->taken_at !== null ? ', pris le '.$media->taken_at->format('d/m/Y à H:i') : '').'.';
            })
            ->all();
    }

    /**
     * Les images de la bibliothèque auxquelles les fichiers ressemblent.
     *
     * @param  array<string, mixed>  $arguments
     * @return \Illuminate\Support\Collection<int, LibraryMedia>
     */
    private function existing(array $arguments): \Illuminate\Support\Collection
    {
        $payload = $this->payload($arguments);

        return LibraryMedia::query()
            ->whereIn('id', array_filter(array_column($payload['files'], 'existing')))
            ->where('model_id', $payload['orchestration'])
            ->inLibrary()
            ->get()
            ->keyBy('id');
    }
}
