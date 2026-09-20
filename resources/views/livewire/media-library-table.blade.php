{{--
    L'image d'une carte est le fond de toute la carte (library/thumbnail) : elle
    est positionnée, donc elle se dessine au-dessus de la case à cocher de
    Filament, qui ne l'est pas. Les classes ci-dessous, pour cette table
    seulement (variantes arbitraires de Tailwind : rien à ajouter au thème) :
    - remontent la case au-dessus de l'image (relative + z-10) ;
    - lui donnent un fond blanc pour qu'elle se voie sur une image sombre. Seulement
      décochée : cochée, Filament la remplit de sa couleur avec une coche blanche,
      qu'un fond blanc rendrait invisible. Le « ! » l'emporte sur le fond
      translucide que Filament lui donne en mode sombre.
    - font de la bibliothèque un conteneur (`@container`) : le nombre de cartes par
      ligne suit sa largeur (voir MediaLibraryTable::SIZES), qu'elle soit dans une
      modale ou dans le volet d'un tiers d'une page.
--}}
<div class="@container [&_.fi-ta-record-checkbox]:relative [&_.fi-ta-record-checkbox]:z-10 [&_.fi-ta-record-checkbox:not(:checked)]:bg-white!">
    {{--
        Au service de quoi la bibliothèque est ouverte : la journée qu'on édite. Elle
        le sait avant même d'être ouverte, la page le lui dit à chaque changement.
    --}}
    @if ($focusTags !== [])
        <div
            data-library-focus
            class="mb-3 flex items-center gap-2 rounded-lg bg-primary-50 px-3 py-2 text-sm text-primary-700 ring-1 ring-primary-600/10 dark:bg-primary-500/10 dark:text-primary-300 dark:ring-primary-400/20"
        >
            <x-filament::icon icon="heroicon-m-map-pin" class="h-4 w-4 shrink-0" />
            <span class="min-w-0 truncate">En cours : <strong class="font-semibold">{{ $this->focusLabel }}</strong></span>
        </div>
    @endif

    {{ $this->table }}
</div>
