@php
    $pane = $getChildSchema('pane');
    $paneKey = $getPaneKey();
    $heading = $getPaneHeading();
    $icon = $getPaneIcon();
    $default = $getPaneWidth();
@endphp

{{--
    Deux blocs côte à côte à partir de `lg`, l'un sous l'autre en dessous. L'état
    (largeur du volet, glissement en cours) vit dans Alpine, sur cette racine, qui
    survit aux rafraîchissements Livewire : ouvrir ou fermer le volet ne remet donc
    pas la largeur choisie à zéro. Il n'y a pas de composant JS à publier : tout
    tient ici, sans `filament:assets`.

    La largeur passe par une variable CSS (`--pane-width`), lue seulement à partir
    de `lg` ; le rendu serveur pose déjà la valeur par défaut, pour qu'il n'y ait
    pas de saut avant qu'Alpine reprenne la main.
--}}
<div
    x-data="{
        width: $persist(@js($default)).as(@js('orchestrator-split-'.$getStorageKey())),
        fallback: @js($default),
        min: @js($getMinPaneWidth()),
        max: @js($getMaxPaneWidth()),
        dragging: false,
        init() {
            this.width = this.clamp(this.width)
        },
        clamp(value) {
            return Math.round(Math.min(this.max, Math.max(this.min, Number(value) || this.fallback)) * 10) / 10
        },
        startDrag(event) {
            this.dragging = true
            event.currentTarget.setPointerCapture(event.pointerId)
        },
        drag(event) {
            if (! this.dragging) return

            const box = this.$refs.root.getBoundingClientRect()
            const bar = event.currentTarget.offsetWidth

            // Le volet est à droite : sa largeur est ce qui reste entre le pointeur
            // (au milieu de la barre) et le bord droit de la racine.
            this.width = this.clamp(((box.right - event.clientX - bar / 2) / box.width) * 100)
        },
        stopDrag(event) {
            this.dragging = false
            event.currentTarget.releasePointerCapture?.(event.pointerId)
        },
    }"
    x-ref="root"
    x-bind:class="{ 'select-none': dragging }"
    {{
        $attributes
            ->merge($getExtraAttributes(), escape: false)
            ->class(['fi-orchestrator-split flex flex-col gap-6 lg:flex-row lg:items-start lg:gap-0'])
    }}
    data-split-view
>
    <div class="fi-orchestrator-split-main min-w-0 flex-1">
        {{ $getChildSchema('main') }}
    </div>

    @if ($pane)
        {{--
            La barre de séparation : on la glisse à la souris ou au doigt, ou on la
            règle aux flèches quand elle a le focus. Un double-clic remet la largeur
            par défaut. Le trait ne s'épaissit qu'au survol, pour rester discret.
        --}}
        <div
            role="separator"
            aria-orientation="vertical"
            aria-label="Redimensionner le volet"
            tabindex="0"
            data-split-handle
            x-bind:aria-valuenow="Math.round(width)"
            x-bind:aria-valuemin="min"
            x-bind:aria-valuemax="max"
            x-on:pointerdown.prevent="startDrag($event)"
            x-on:pointermove="drag($event)"
            x-on:pointerup="stopDrag($event)"
            x-on:pointercancel="stopDrag($event)"
            x-on:dblclick="width = fallback"
            x-on:keydown.left.prevent="width = clamp(width + 2)"
            x-on:keydown.right.prevent="width = clamp(width - 2)"
            x-on:keydown.home.prevent="width = max"
            x-on:keydown.end.prevent="width = min"
            class="group hidden w-3 shrink-0 cursor-col-resize touch-none items-stretch justify-center self-stretch outline-none lg:flex"
        >
            <span
                class="w-px rounded-full bg-gray-200 transition group-hover:w-0.5 group-hover:bg-primary-500 group-focus-visible:w-0.5 group-focus-visible:bg-primary-500 dark:bg-white/10"
                x-bind:class="dragging ? 'w-0.5! bg-primary-500!' : ''"
            ></span>
        </div>

        {{--
            Le volet reste dans la page pendant qu'on fait défiler un long formulaire,
            et défile de son côté s'il est plus haut que l'écran : on garde ainsi les
            deux blocs sous les yeux, ce qui est tout l'intérêt du glisser-déposer.
        --}}
        <aside
            wire:key="orchestrator-split-pane-{{ $paneKey }}"
            data-split-pane="{{ $paneKey }}"
            style="--pane-width: {{ $default }}%"
            x-bind:style="{ '--pane-width': width + '%' }"
            class="fi-orchestrator-split-pane min-w-0 rounded-xl bg-white shadow-xs ring-1 ring-gray-950/5 lg:sticky lg:top-20 lg:z-40 lg:max-h-[calc(100dvh-6rem)] lg:shrink-0 lg:basis-(--pane-width) lg:overflow-y-auto dark:bg-gray-900 dark:ring-white/10"
        >
            <div class="flex items-center gap-2 border-b border-gray-200 px-4 py-2.5 dark:border-white/10">
                @if (filled($icon))
                    <x-filament::icon :icon="$icon" class="h-5 w-5 shrink-0 text-gray-400 dark:text-gray-500" />
                @endif

                <h2 class="min-w-0 flex-1 truncate text-sm font-semibold text-gray-950 dark:text-white">
                    {{ $heading }}
                </h2>

                <x-filament::icon-button
                    icon="heroicon-m-x-mark"
                    color="gray"
                    size="sm"
                    label="Fermer le volet"
                    wire:click="closeSidePane"
                />
            </div>

            <div class="fi-orchestrator-split-pane-body">
                {{ $pane }}
            </div>
        </aside>
    @endif
</div>
