{{--
    L'aperçu d'une tuile des panneaux d'images : l'image, ou — pour une vidéo — son image d'ouverture (lue par le
    navigateur : le serveur n'en fait aucun aperçu) avec l'icône de lecture. Même taille dans les deux cas.
--}}
@if ($media->isPlayable())
    <div class="relative h-20 w-20 overflow-hidden rounded-lg bg-black" data-library-video-tile>
        @if ($media->isVideo())
            <video
                src="{{ $media->getUrl() }}#t=0.1"
                preload="metadata"
                muted
                playsinline
                draggable="false"
                class="h-full w-full object-contain"
            ></video>
        @else
            <img
                src="{{ $media->thumbUrl() }}"
                alt="{{ $media->name }}"
                loading="lazy"
                draggable="false"
                class="h-full w-full object-contain"
            />
        @endif
        <span class="pointer-events-none absolute inset-0 flex items-center justify-center">
            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-black/55 text-white shadow backdrop-blur-sm">
                <x-filament::icon icon="heroicon-s-play" class="h-3.5 w-3.5 translate-x-px" />
            </span>
        </span>
    </div>
@else
    <img
        src="{{ $media->thumbUrl() }}"
        alt="{{ $media->name }}"
        loading="lazy"
        draggable="false"
        class="h-20 w-20 rounded-lg object-cover"
    />
    {{--
        Vignette encore en fabrication (file d'attente) : l'original s'affiche en attendant, le panneau se redessine. Au
        centre : les coins portent déjà l'étoile, la place, le cadrage et le bouton pour retirer.
    --}}
    @if ($media->isOptimizing())
        <span
            data-library-optimizing
            title="Optimisation en cours"
            class="pointer-events-none absolute inset-0 flex items-center justify-center"
        >
            <span class="flex items-center rounded-full bg-black/55 p-1 text-white shadow backdrop-blur-sm">
                <x-filament::loading-indicator class="h-3.5 w-3.5" />
            </span>
        </span>
    @endif
@endif
