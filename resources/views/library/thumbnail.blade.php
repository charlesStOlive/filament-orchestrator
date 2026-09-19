@php
    $media = $getRecord();
    $url = $media->thumbUrl();
@endphp

{{-- Toute la carte ouvre la fiche : le crayon n'est qu'un repère, posé dans le coin de l'image. --}}
<div class="relative">
    <img
        src="{{ $url }}"
        alt="{{ $media->name }}"
        loading="lazy"
        class="aspect-square w-full rounded-lg object-cover"
    />

    <span
        data-library-edit-mark
        class="pointer-events-none absolute bottom-2 right-2 rounded-full bg-white/90 p-1.5 text-gray-700 shadow dark:bg-gray-900/90 dark:text-gray-200"
    >
        <x-filament::icon icon="heroicon-m-pencil-square" class="h-4 w-4" />
    </span>
</div>
