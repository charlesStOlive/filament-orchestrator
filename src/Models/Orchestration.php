<?php

namespace CharlesStOlive\FilamentOrchestrator\Models;

use CharlesStOlive\FilamentOrchestrator\Library\DeletionGuards;
use CharlesStOlive\FilamentOrchestrator\Library\MediaInUse;
use CharlesStOlive\FilamentOrchestrator\Registry\SchemaRegistry;
use CharlesStOlive\FilamentOrchestrator\Schemas\OrchestratorSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Orchestration extends Model implements HasMedia
{
    use InteractsWithMedia;

    /**
     * Toutes les images d'une orchestration vivent ici, au niveau du modèle.
     * Ce qui les rattache à un contenu précis (une journée, une introduction),
     * ce sont leurs tags, pas leur emplacement.
     */
    public const LIBRARY_COLLECTION = 'library';

    protected $fillable = [
        'schema',
        'name',
        'key',
        'description',
        'event_scope',
        'config',
        'initial_state',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'initial_state' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Les images partent avec l'orchestration (medialibrary s'en charge) :
        // il ne reste que les tags de sa bibliothèque à balayer.
        static::deleted(fn (self $orchestration) => LibraryTag::query()
            ->where('type', $orchestration->libraryTagType())
            ->delete());
    }

    public function getTable(): string
    {
        return config('filament-orchestrator.tables.orchestrations', parent::getTable());
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::LIBRARY_COLLECTION)
            ->useDisk(config('filament-orchestrator.library.disk', 'public'))
            ->acceptsMimeTypes([...LibraryMedia::IMAGE_TYPES, ...LibraryMedia::VIDEO_TYPES]);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $thumb = $this->addMediaConversion('thumb')
            ->performOnCollections(self::LIBRARY_COLLECTION);

        // Un cadrage choisi (LibraryMedia::setFocus()) : réduite par son petit côté, l'image est découpée en carré de
        // son côté. Sinon, découpée au centre.
        if ($media instanceof LibraryMedia && ($crop = $media->thumbCrop()) !== null) {
            $crop['landscape'] ? $thumb->height(480) : $thumb->width(480);
            $thumb->crop(480, 480, $crop['position']);
        } else {
            $thumb->fit(Fit::Crop, 480, 480);
        }

        // Les tailles d'affichage : un original de smartphone pèse plusieurs
        // Mo, un front ne doit jamais l'envoyer tel quel. Le côté le plus long
        // est borné, le format de l'image est respecté (pas de recadrage).
        $medium = $this->addMediaConversion('medium')
            ->performOnCollections(self::LIBRARY_COLLECTION)
            ->fit(Fit::Max, 900, 900);

        $large = $this->addMediaConversion('large')
            ->performOnCollections(self::LIBRARY_COLLECTION)
            ->fit(Fit::Max, 1800, 1800);

        // Par défaut synchrone : sans worker de queue, une vignette mise en
        // file d'attente n'apparaîtrait jamais.
        foreach ([$thumb, $medium, $large] as $conversion) {
            config('filament-orchestrator.library.queue_conversions', false)
                ? $conversion->queued()
                : $conversion->nonQueued();
        }
    }

    /** Les images de la bibliothèque, et elles seules. */
    public function libraryMedia(): MorphMany
    {
        return $this->media()->where('collection_name', self::LIBRARY_COLLECTION);
    }

    /**
     * Supprimer l'orchestration emporte ses fichiers, un à un (medialibrary). Si une garde en retient un (une version
     * publiée le montre…), rien ne part : on le vérifie pour tous avant le premier, sinon ceux d'avant seraient déjà
     * effacés quand le fichier retenu arrêterait la suppression (voir LibraryMedia).
     *
     * Ses nœuds partent avec elle (clé étrangère), et les modèles qui n'appartiennent qu'à elle (`owned` : ses
     * contenus, ses hotpoints…) aussi, comme quand on retire un de ses nœuds (NodeSynchronizer). Les modèles liés
     * (`linked`, une scène partagée…) restent.
     */
    public function delete(): ?bool
    {
        $guards = app(DeletionGuards::class);

        foreach ($this->libraryMedia()->cursor() as $media) {
            if ($media instanceof LibraryMedia && ($reason = $guards->reason($media)) !== null) {
                throw new MediaInUse($media, $reason);
            }
        }

        return DB::transaction(function (): ?bool {
            $owned = $this->nodes()->with('orchestratable')->get()
                ->filter(fn (OrchestratorNode $node): bool => $node->isOwned() && $node->orchestratable instanceof Model)
                ->map(fn (OrchestratorNode $node): Model => $node->orchestratable);

            $deleted = parent::delete();

            $owned->each(fn (Model $model) => $model->delete());

            return $deleted;
        });
    }

    /** Type sous lequel sont rangés les tags de cette bibliothèque : un type par orchestration. */
    public function libraryTagType(): string
    {
        return 'orchestration-'.$this->getKey();
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(OrchestratorNode::class)->orderBy('sort_order')->orderBy('id');
    }

    public function triggers(): HasMany
    {
        return $this->hasMany(OrchestratorTrigger::class)->orderBy('sort_order')->orderBy('id');
    }

    public function scope(): string
    {
        return $this->event_scope ?: 'orchestration-'.$this->getKey();
    }

    public function schemaDefinition(): OrchestratorSchema
    {
        return app(SchemaRegistry::class)->get($this->schema);
    }
}
