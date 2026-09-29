{{-- Le cadrage choisi d'une vignette des panneaux d'images, en bas à droite : sa flèche. Rien au centre (l'origine). --}}
@if ($icon = $this->focusIcon($media))
    <span
        data-library-focus="{{ $media->focus() }}"
        class="pointer-events-none absolute bottom-1 right-1 flex items-center rounded-full bg-black/60 p-0.5 text-white shadow"
    >
        <x-filament::icon :icon="$icon" class="h-3 w-3" />
    </span>
@endif
