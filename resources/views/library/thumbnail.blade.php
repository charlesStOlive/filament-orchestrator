@php
    $media = $getRecord();
    $small = $size === 's';
    $chip = 'absolute flex items-center gap-0.5 rounded-full bg-white/90 text-gray-700 shadow dark:bg-gray-900/90 dark:text-gray-200';
    $icon = $small ? 'h-3 w-3' : 'h-4 w-4';
@endphp

{{--
    Toute la carte ouvre la fiche : le crayon n'est qu'un repère, posé dans le
    coin de l'image. En petit format, il n'y a plus de texte sous l'image : les
    tags, la position et l'édition se lisent en icônes.
--}}
<div class="relative">
    <img
        src="{{ $media->thumbUrl() }}"
        alt="{{ $media->name }}"
        title="{{ $media->taken_at?->format('d/m/Y H:i') ?? 'Sans date' }}"
        loading="lazy"
        class="aspect-square w-full rounded-lg object-cover"
    />

    @if ($small && $tagLabels !== [])
        <span
            data-library-tags-mark
            x-tooltip="{ content: @js(implode(', ', $tagLabels)), theme: $store.theme }"
            class="{{ $chip }} left-1 top-1 px-1.5 py-0.5 text-[10px] font-semibold"
        >
            <x-filament::icon icon="heroicon-m-tag" class="{{ $icon }}" />
            {{ count($tagLabels) }}
        </span>
    @endif

    @if ($small && $media->hasGps())
        <span
            data-library-gps-mark
            x-tooltip="{ content: 'Position GPS', theme: $store.theme }"
            class="{{ $chip }} bottom-1 left-1 p-1"
        >
            <x-filament::icon icon="heroicon-m-map-pin" class="{{ $icon }}" />
        </span>
    @endif

    <span
        data-library-edit-mark
        class="{{ $chip }} pointer-events-none {{ $small ? 'bottom-1 right-1 p-1' : 'bottom-2 right-2 p-1.5' }}"
    >
        <x-filament::icon icon="heroicon-m-pencil-square" class="{{ $icon }}" />
    </span>
</div>
