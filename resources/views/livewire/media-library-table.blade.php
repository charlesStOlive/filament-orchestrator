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
--}}
<div class="[&_.fi-ta-record-checkbox]:relative [&_.fi-ta-record-checkbox]:z-10 [&_.fi-ta-record-checkbox:not(:checked)]:bg-white!">
    {{ $this->table }}
</div>
