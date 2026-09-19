@php
    $media = $getRecord();
    $small = $size === 's';
    $date = $media->taken_at?->format('d/m/Y H:i');
    $key = $media->getKey();
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

    Toute la carte ouvre la fiche : le crayon n'est qu'un repère. La poubelle,
    elle, est un vrai bouton : `.stop` l'empêche d'ouvrir aussi la fiche. En
    petit format il n'y a pas de texte : tags et position se lisent en icônes.
--}}
<div class="aspect-square w-full" data-library-card="{{ $key }}">
    <img
        src="{{ $media->thumbUrl() }}"
        alt="{{ $media->name }}"
        title="{{ $date ?? 'Sans date' }}"
        loading="lazy"
        class="absolute inset-0 h-full w-full rounded-xl object-cover"
    />

    @unless ($small)
        <div class="pointer-events-none absolute inset-x-0 bottom-0 h-3/5 rounded-b-xl bg-gradient-to-t from-black/80 to-transparent"></div>
    @endunless

    {{-- Une carte cochée se voit : le fond de carte de Filament est sous l'image. --}}
    <span
        class="pointer-events-none absolute inset-0 rounded-xl"
        x-bind:class="isRecordSelected('{{ $key }}') ? 'ring-4 ring-inset ring-primary-500 bg-primary-500/25' : ''"
    ></span>

    @if ($small)
        @if ($tagLabels !== [])
            <span
                data-library-tags-mark
                x-tooltip="{ content: @js(implode(', ', $tagLabels)), theme: $store.theme }"
                class="{{ $chip }} absolute left-1.5 top-1.5 px-1.5 py-0.5 text-[10px] font-semibold"
            >
                <x-filament::icon icon="heroicon-m-tag" class="{{ $icon }}" />
                {{ count($tagLabels) }}
            </span>
        @endif

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
