@php
    $media = $getRecord();
    $small = $size === 's';
    $date = $media->taken_at?->format('d/m/Y H:i');
    $key = $media->getKey();
    $thumbUrl = $media->thumbUrl();
    $fullUrl = $media->conversionUrl('medium');
    // Puces sur fond sombre : lisibles sur n'importe quelle photo.
    $chip = 'flex items-center gap-0.5 rounded-full bg-black/55 text-white shadow backdrop-blur-sm';
    $icon = $small ? 'h-3 w-3' : 'h-4 w-4';
    $pad = $small ? 'p-1' : 'p-1.5';
@endphp

{{--
    L'image est le fond de toute la carte, sous la case à cocher. Cette
    enveloppe n'est pas positionnée : les éléments ci-dessous se placent par
    rapport à la carte elle-même (`fi-ta-record`, qui est `relative`). Elle
    donne sa hauteur à la carte, avec un carré de la largeur du contenu.

    Toute la carte se glisse : déposée dans la case de fin du panneau d'images d'une
    période, l'image s'y ajoute ; déposée dans un texte, elle y écrit sa référence
    « (image N) » et s'ajoute aussi à la période (voir LibraryImages::DRAG_TYPE). Le
    glisser porte la clé de chaque fichier glissé, sa nature (image ou vidéo) et celle du voyage, au format JSON — et
    plusieurs quand la carte est cochée avec d'autres : on les glisse toutes ensemble.

    Toute la carte ouvre la fiche : le crayon n'est qu'un repère. La poubelle,
    elle, est un vrai bouton : `.stop` l'empêche d'ouvrir aussi la fiche. En
    petit format il n'y a pas de texte : tags et position se lisent en icônes.
--}}
<div
    class="group aspect-square w-full cursor-grab active:cursor-grabbing"
    data-library-card="{{ $key }}"
    data-library-kind="{{ $media->kind() }}"
    draggable="true"
    x-on:dragstart="
        $event.dataTransfer.effectAllowed = 'copy'

        // Une carte cochée entraîne toutes les cochées, dans l'ordre où on les a cochées ; sinon, elle seule.
        const keys = isRecordSelected('{{ $key }}') && selectedRecords.size > 1 ? [...selectedRecords] : ['{{ $key }}']
        const items = keys.map((key) => ({
            media: Number(key),
            kind: document.querySelector(`[data-library-card='${key}']`)?.dataset.libraryKind ?? 'image',
        }))

        $event.dataTransfer.setData(@js($dragType), JSON.stringify({ orchestration: {{ $orchestrationId }}, items }))

        // Plusieurs cartes : le fantôme du glisser dit combien.
        if (items.length > 1) {
            const ghost = document.createElement('div')
            ghost.textContent = items.length + ' fichiers'
            ghost.style.cssText = 'position:fixed;top:-100px;padding:6px 12px;border-radius:9999px;background:#1f2937;color:#fff;font:600 13px sans-serif'
            document.body.append(ghost)
            $event.dataTransfer.setDragImage(ghost, 12, 12)
            setTimeout(() => ghost.remove())
        }
    "
    @unless ($fit)
        x-data="{ full: false }"
        x-on:mouseenter.once="full = true"
    @endunless
>
    @if ($media->isVideo())
        {{--
            Une vidéo : le navigateur en montre l'image d'ouverture (`preload="metadata"`, `#t=0.1`), le serveur n'en fait
            aucun aperçu. Ni recadrage ni image entière au survol : elle se montre entière, sur fond noir. L'icône de
            lecture la distingue d'une image, et sa durée — lue par le navigateur — s'inscrit dès qu'il la connaît.
        --}}
        <div
            data-library-video="{{ $key }}"
            x-data="{ duration: null }"
            class="absolute inset-0 overflow-hidden rounded-xl bg-black"
        >
            <video
                src="{{ $media->getUrl() }}#t=0.1"
                preload="metadata"
                muted
                playsinline
                draggable="false"
                x-on:loadedmetadata="duration = Number.isFinite($el.duration) ? Math.round($el.duration) : null"
                class="h-full w-full object-contain"
            ></video>

            <span class="pointer-events-none absolute inset-0 flex items-center justify-center">
                <span class="flex items-center justify-center rounded-full bg-black/55 text-white shadow backdrop-blur-sm {{ $small ? 'h-7 w-7' : 'h-12 w-12' }}">
                    <x-filament::icon icon="heroicon-s-play" class="{{ $small ? 'h-3.5 w-3.5' : 'h-6 w-6' }} translate-x-px" />
                </span>
            </span>

            <span
                x-show="duration !== null"
                x-cloak
                x-text="Math.floor(duration / 60) + ':' + String(duration % 60).padStart(2, '0')"
                data-library-duration
                class="pointer-events-none absolute right-1.5 {{ $small ? 'bottom-1.5 text-[10px]' : 'bottom-10 text-xs' }} rounded bg-black/65 px-1.5 py-0.5 font-medium leading-none text-white"
            ></span>
        </div>
    @else
    {{--
        Recadrée (`object-cover`) pour remplir la carte, l'image se montre entière
        (`object-contain`) au survol de la carte — et en permanence quand on a
        figé l'option « Image entière » ($fit). Les bandes que l'image entière
        laisse libres prennent un fond neutre, pour ne pas laisser voir le fond
        de carte de Filament.

        La vignette (`thumb`) est recadrée en carré à sa fabrication : elle ne
        peut pas montrer l'image entière. Il faut la taille d'affichage `medium`,
        qui garde les proportions. Figé, la carte la charge d'emblée ; sinon elle
        ne la charge qu'au premier survol (un carré recadré reste sous les yeux
        le temps qu'elle arrive), pour ne pas tirer toute la page en 900 px.
    --}}
    <img
        src="{{ $fit ? $fullUrl : $thumbUrl }}"
        @unless ($fit)
            x-bind:src="full ? @js($fullUrl) : @js($thumbUrl)"
        @endunless
        alt="{{ $media->name }}"
        title="{{ $date ?? 'Sans date' }}"
        loading="lazy"
        draggable="false"
        data-library-fit="{{ $fit ? 'frozen' : 'hover' }}"
        class="absolute inset-0 h-full w-full rounded-xl bg-gray-100 dark:bg-gray-800 {{ $fit ? 'object-contain' : 'object-cover group-hover:object-contain' }}"
    />
    @endif

    @unless ($small)
        <div class="pointer-events-none absolute inset-x-0 bottom-0 h-3/5 rounded-b-xl bg-gradient-to-t from-black/80 to-transparent"></div>
    @endunless

    {{-- Une carte cochée se voit : le fond de carte de Filament est sous l'image. --}}
    <span
        class="pointer-events-none absolute inset-0 rounded-xl"
        x-bind:class="isRecordSelected('{{ $key }}') ? 'ring-4 ring-inset ring-primary-500 bg-primary-500/25' : ''"
    ></span>

    {{--
        En haut à gauche, en colonne : le nombre de tags (petit format seulement,
        les autres formats nomment leurs tags), puis la marque de chaque action de
        l'application qui concerne cette image (l'étoile d'une image d'en-tête, par
        exemple), avec son libellé au survol.
    --}}
    <div class="pointer-events-none absolute flex flex-col items-start gap-1 {{ $small ? 'left-1.5 top-1.5' : 'left-2 top-2' }}">
        @if ($small && $tagLabels !== [])
            <span
                data-library-tags-mark
                x-tooltip="{ content: @js(implode(', ', $tagLabels)), theme: $store.theme }"
                class="{{ $chip }} pointer-events-auto px-1.5 py-0.5 text-[10px] font-semibold"
            >
                <x-filament::icon icon="heroicon-m-tag" class="{{ $icon }}" />
                {{ count($tagLabels) }}
            </span>
        @endif

        @foreach ($marks as $mark)
            <span
                data-library-mark
                aria-label="{{ $mark['label'] }}"
                x-tooltip="{ content: @js($mark['label']), theme: $store.theme }"
                class="flex items-center rounded-full bg-primary-600/90 text-white shadow pointer-events-auto {{ $pad }}"
            >
                <x-filament::icon icon="{{ $mark['icon'] }}" class="{{ $icon }}" />
            </span>
        @endforeach
    </div>

    @if ($small)
        @if ($media->hasGps())
            <span
                data-library-gps-mark
                x-tooltip="{ content: 'Position GPS', theme: $store.theme }"
                class="{{ $chip }} absolute bottom-1.5 left-1.5 p-1"
            >
                <x-filament::icon icon="heroicon-m-map-pin" class="{{ $icon }}" />
            </span>
        @endif
    @else
        <div class="absolute inset-x-0 bottom-0 flex flex-col gap-1.5 px-3 pb-2.5 text-white">
            @if ($tags['badges'] !== [])
                <div
                    class="flex flex-wrap gap-1"
                    @if ($tags['overflow'] !== null)
                        x-tooltip="{ content: @js(implode(', ', $tagLabels)), theme: $store.theme }"
                    @endif
                >
                    @foreach ($tags['badges'] as $badge)
                        <span
                            data-library-tag
                            class="rounded-md px-1.5 py-0.5 text-[10px] font-medium leading-none ring-1 ring-white/25 {{ $badge === $tags['overflow'] ? 'bg-white/85 text-gray-900' : 'bg-black/55 text-white' }}"
                        >{{ $badge }}</span>
                    @endforeach
                </div>
            @endif

            <div class="flex items-center justify-between text-[11px] leading-none text-white/85">
                <span>{{ $date ?? 'Sans date' }}</span>

                @if ($media->hasGps())
                    <span data-library-gps-mark x-tooltip="{ content: 'Position GPS', theme: $store.theme }">
                        <x-filament::icon icon="heroicon-m-map-pin" class="h-4 w-4" />
                    </span>
                @endif
            </div>
        </div>
    @endif

    {{-- Le crayon (repère) puis la poubelle (bouton), en colonne dans le coin. --}}
    <div class="pointer-events-none absolute flex flex-col gap-1 {{ $small ? 'right-1.5 top-1.5' : 'right-2 top-2' }}">
        <span data-library-edit-mark class="{{ $chip }} {{ $pad }}">
            <x-filament::icon icon="heroicon-m-pencil-square" class="{{ $icon }}" />
        </span>

        <span
            role="button"
            data-library-delete-mark
            aria-label="Supprimer cette image"
            x-tooltip="{ content: 'Supprimer', theme: $store.theme }"
            wire:click.stop="mountTableAction('deleteImage', '{{ $key }}')"
            class="{{ $chip }} {{ $pad }} pointer-events-auto cursor-pointer transition hover:bg-danger-600"
        >
            <x-filament::icon icon="heroicon-m-trash" class="{{ $icon }}" />
        </span>
    </div>
</div>
