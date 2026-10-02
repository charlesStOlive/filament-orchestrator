{{--
    Au service de quoi la bibliothèque est ouverte : la période qu'on édite. Elle le sait avant même d'être ouverte, la
    page le lui dit à chaque changement. C'est l'en-tête de la table (Table::header), donc dans le bloc qui reste
    visible quand la grille défile, avec les filtres et les boutons.

    Quand l'application sait quelles dates elle couvre (LibraryTagDates), un bouton filtre la bibliothèque sur ces dates
    de prise de vue — ou retire ce filtre, s'il y est déjà.
--}}
@php
    $format = fn (string $date): string => \Carbon\CarbonImmutable::parse($date)->format('d/m/Y');
    $period = $dates === null ? null : ($dates['from'] === $dates['until']
        ? 'le '.$format($dates['from'])
        : 'du '.$format($dates['from']).' au '.$format($dates['until']));
@endphp
<div
    data-library-focus
    class="mb-2 flex flex-wrap items-center gap-x-3 gap-y-1.5 rounded-lg bg-primary-50 px-3 py-2 text-sm text-primary-700 ring-1 ring-primary-600/10 dark:bg-primary-500/10 dark:text-primary-300 dark:ring-primary-400/20"
>
    <span class="flex min-w-0 flex-1 items-center gap-2">
        <x-filament::icon icon="heroicon-m-map-pin" class="h-4 w-4 shrink-0" />
        <span class="min-w-0 truncate">En cours : <strong class="font-semibold">{{ $label }}</strong></span>
    </span>

    @if ($period !== null)
        <x-filament::button
            data-library-focus-dates
            size="xs"
            :color="$filtered ? 'primary' : 'gray'"
            :outlined="! $filtered"
            :icon="$filtered ? 'heroicon-m-x-mark' : 'heroicon-m-calendar-days'"
            wire:click="toggleFocusDates"
            :tooltip="$filtered ? 'Retirer le filtre des dates de prise de vue' : 'Ne montrer que les images prises '.$period"
        >
            {{ $filtered ? 'Dates : '.$period : 'Filtrer '.$period }}
        </x-filament::button>
    @endif
</div>
