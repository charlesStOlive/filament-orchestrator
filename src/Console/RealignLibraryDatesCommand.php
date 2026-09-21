<?php

namespace CharlesStOlive\FilamentOrchestrator\Console;

use CharlesStOlive\FilamentOrchestrator\Library\MediaMetadata;
use CharlesStOlive\FilamentOrchestrator\Library\MetadataExtractor;
use CharlesStOlive\FilamentOrchestrator\Models\LibraryMedia;
use Illuminate\Console\Command;

/**
 * Recalcule la date de prise de vue des images et vidéos dont elle vient du fichier lui-même (EXIF d'une photo,
 * métadonnées d'une vidéo), à partir du fichier d'origine.
 *
 * Pourquoi : dans une requête web, une application qui ramène les dates au fuseau de l'utilisateur (`Date::useCallable()`)
 * décalait l'heure à l'écriture : une photo prise le 5 mai à 22 h 03 était enregistrée le 6 mai à 00 h 03, et rangée dans la
 * mauvaise période (voir LibraryMedia::takenAt()). Le défaut est corrigé pour les prochains envois ; cette commande répare
 * ceux qui l'ont subi.
 *
 * Elle ne touche que les dates que le fichier sait redonner (source « exif » ou « video », ou non renseignée pour les plus
 * anciennes) : une date saisie à la main n'est jamais modifiée, et une date « du fichier » (celle du chargement) ne l'est que
 * pour une vidéo dont les métadonnées donnent enfin la vraie date de tournage. Elle ne change pas les
 * tags : une image rangée dans une période à cause d'une date décalée y reste, à vous de la déplacer.
 *
 *     php artisan orchestrator:realign-library-dates --dry-run     # montre ce qui changerait, sans rien écrire
 *     php artisan orchestrator:realign-library-dates --voyage=3
 */
class RealignLibraryDatesCommand extends Command
{
    protected $signature = 'orchestrator:realign-library-dates
        {--voyage= : Ne traiter que la bibliothèque de ce voyage (son identifiant)}
        {--dry-run : Montrer ce qui changerait, sans rien écrire}';

    protected $description = 'Recalcule la date de prise de vue des images et vidéos depuis leur fichier (répare le décalage de fuseau)';

    public function handle(MetadataExtractor $extractor): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $query = LibraryMedia::query()->inLibrary();

        if (filled($this->option('voyage'))) {
            $query->where('model_id', (int) $this->option('voyage'));
        }

        $rows = [];
        $examined = 0;
        $unchanged = 0;
        $skipped = 0;

        $query->orderBy('id')->each(function (LibraryMedia $media) use ($extractor, $dryRun, &$rows, &$examined, &$unchanged, &$skipped): void {
            $examined++;

            $source = $media->getCustomProperty('date_source');

            // Saisie à la main : c'est la volonté de quelqu'un. Datée du chargement : on ne peut la relire dans le fichier
            // — sauf pour une vidéo, dont les métadonnées (lues seulement depuis peu) donnent peut-être la vraie date.
            if ($source === MediaMetadata::SOURCE_MANUAL || ($source === MediaMetadata::SOURCE_FILE && ! $media->isVideo())) {
                $skipped++;

                return;
            }

            $path = $media->getPath();
            $metadata = is_file($path) ? $extractor->extract($path) : new MediaMetadata;

            // Rien de fiable à relire (le fichier n'a plus d'EXIF, ou il manque) : on ne devine pas.
            if ($metadata->takenAt === null || $metadata->dateSource === MediaMetadata::SOURCE_FILE) {
                $skipped++;

                return;
            }

            $current = $media->getRawOriginal('taken_at');
            $expected = $metadata->takenAt->format('Y-m-d H:i:s');
            $upgraded = $source === MediaMetadata::SOURCE_FILE;

            if ($current === $expected && ! $upgraded) {
                $unchanged++;

                return;
            }

            $rows[] = [$media->getKey(), $media->file_name, $current ?? '—', $expected.($upgraded ? ' (date de tournage)' : '')];

            if ($dryRun) {
                return;
            }

            // La date fiable d'une vidéo remplace celle du chargement : elle devient une date de tournage.
            if ($upgraded) {
                $media->setCustomProperty('date_source', MediaMetadata::SOURCE_VIDEO)->saveQuietly();
            }

            // Directement en base : sans passer par Eloquent, dont les dates sont ce qu'on répare.
            LibraryMedia::query()->whereKey($media->getKey())->toBase()->update(array_filter([
                'taken_at' => $expected,
                // Une position que la vidéo porte et que la bibliothèque n'avait pas.
                'latitude' => $media->latitude === null ? $metadata->latitude : null,
                'longitude' => $media->longitude === null ? $metadata->longitude : null,
            ], fn (mixed $value): bool => $value !== null));
        });

        if ($rows !== []) {
            $this->table(['Id', 'Fichier', $dryRun ? 'Actuelle' : 'Avant', $dryRun ? 'Serait' : 'Après'], $rows);
        }

        $this->info(sprintf(
            '%d fichier(s) examiné(s) : %d %s, %d déjà justes, %d laissés tels quels (date du chargement, saisie à la main, ou illisible).',
            $examined,
            count($rows),
            $dryRun ? 'à corriger (simulation : rien n’est écrit)' : 'corrigé(s)',
            $unchanged,
            $skipped,
        ));

        return self::SUCCESS;
    }
}
