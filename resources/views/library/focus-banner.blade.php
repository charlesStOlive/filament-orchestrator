{{--
    Au service de quoi la bibliothèque est ouverte : la période qu'on édite. Elle le sait avant même d'être ouverte, la
    page le lui dit à chaque changement. C'est l'en-tête de la table (Table::header), donc dans le bloc qui reste
    visible quand la grille défile, avec les filtres et les boutons.
--}}
<div
    data-library-focus
    class="mb-2 flex items-center gap-2 rounded-lg bg-primary-50 px-3 py-2 text-sm text-primary-700 ring-1 ring-primary-600/10 dark:bg-primary-500/10 dark:text-primary-300 dark:ring-primary-400/20"
>
    <x-filament::icon icon="heroicon-m-map-pin" class="h-4 w-4 shrink-0" />
    <span class="min-w-0 truncate">En cours : <strong class="font-semibold">{{ $label }}</strong></span>
</div>
