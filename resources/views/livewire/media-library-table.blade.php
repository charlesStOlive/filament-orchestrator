{{--
    L'image d'une carte est le fond de toute la carte (library/thumbnail) : elle
    est positionnée, donc elle se dessine au-dessus de la case à cocher de
    Filament, qui ne l'est pas. Les deux classes ci-dessous remontent la case
    au-dessus de l'image, pour cette table seulement (variante arbitraire de
    Tailwind : rien à ajouter au thème).
--}}
<div class="[&_.fi-ta-record-checkbox]:relative [&_.fi-ta-record-checkbox]:z-10">
    {{ $this->table }}
</div>
