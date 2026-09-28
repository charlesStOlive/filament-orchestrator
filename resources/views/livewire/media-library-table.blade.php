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
{{--
    Le bloc du haut — au service de quoi la bibliothèque est ouverte, filtres, envoi, sélection, tailles — reste
    visible quand la grille défile (`sticky`, dans le volet ou la modale qui défile). Le conteneur de la table a
    `overflow: hidden` pour arrondir ses coins : il ferait de lui le défilement et empêcherait `sticky` ; `clip`
    arrondit de même, sans en être un.
--}}
<div class="@container [&_.fi-ta-record-checkbox]:relative [&_.fi-ta-record-checkbox]:z-10 [&_.fi-ta-record-checkbox:not(:checked)]:bg-white! [&_.fi-ta-ctn]:overflow-clip [&_.fi-ta-header-ctn]:sticky [&_.fi-ta-header-ctn]:top-0 [&_.fi-ta-header-ctn]:z-20 [&_.fi-ta-header-ctn]:bg-white [&_.fi-ta-header-ctn]:pb-2 dark:[&_.fi-ta-header-ctn]:bg-gray-900">
    {{ $this->table }}
</div>
