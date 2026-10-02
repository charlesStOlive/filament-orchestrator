{{-- Des images en cours d'optimisation (file d'attente) : le panneau se redessine jusqu'à ce que leurs vignettes soient là. --}}
<div class="space-y-3" @if ($this->hasOptimizingMedia) wire:poll.4s @endif>
    {{--
        Comme l'aide d'un champ Filament : un « ? » (lien Filament, couleur info) juste après le titre, son libellé en
        infobulle — ou, avec `helpFullTitle`, le libellé en toutes lettres au bout de la ligne.
    --}}
    <div class="flex items-center justify-between gap-2">
        <div class="flex items-center gap-2 whitespace-nowrap text-sm font-medium text-gray-950 dark:text-white">
            <span>
                {{ $heading }}
                @unless ($single)
                    <span class="font-normal text-gray-500 dark:text-gray-400">({{ $this->images->count() }})</span>
                @endunless
            </span>

            @if ($help && ! $helpFullTitle)
                <x-filament::link
                    :href="'#modal-' . $help"
                    color="info"
                    icon="heroicon-o-question-mark-circle"
                    :size="\Filament\Support\Enums\Size::Small"
                    :tooltip="$helpLabel ?? 'Aide'"
                    label-sr-only
                >
                    {{ $helpLabel ?? 'Aide' }}
                </x-filament::link>
            @endif
        </div>

        @if ($help && $helpFullTitle)
            <x-filament::link
                :href="'#modal-' . $help"
                color="info"
                icon="heroicon-o-question-mark-circle"
                :size="\Filament\Support\Enums\Size::Small"
                class="whitespace-nowrap"
            >
                {{ $helpLabel ?? 'Aide' }}
            </x-filament::link>
        @endif
    </div>

    {{--
        Deux modes. Par défaut, une grille : glisser-déposer natif de Filament (x-sortable), l'ordre est enregistré
        au dépôt. Chaque image porte sa clé (`data-library-image`, celle de la bibliothèque, qui survit aux
        changements d'ordre) et sa place (`data-library-position`, à partir de 1 : le numéro que le carnet lui
        donne). C'est par elles que le texte d'un contenu désigne une image (voir library-image.js). Le survol d'une
        vignette met en évidence ses références dans le texte, et inversement : les deux se le disent par
        l'événement navigateur « {{ $this->hoverEvent() }} ».

        En mode `single` (l'image de « une » d'une période), une seule image : on la remplace, on ne réordonne rien,
        et elle n'a ni clé ni numéro — le texte ne la désigne pas.

        Dans les deux modes, un clic sur une image (sans la glisser : le glisser natif n'émet pas de clic) ouvre son
        cadrage (focusAction) ; une petite flèche en bas à droite dit le cadrage choisi, quand ce n'est pas le centre.
        Les boutons de la vignette (retirer) gardent leur rôle.

        À la suite de la dernière image, toujours, une case « + » : un clic ouvre la bibliothèque (c'est aussi là
        qu'on envoie des fichiers), et on peut y déposer une image glissée depuis la bibliothèque : elle s'ajoute à
        la fin (ou remplace, en mode `single`). Elle ne réagit qu'à ces images-là (pas au réordonnancement d'ici,
        qui a ses propres événements).
    --}}
    {{-- En mode single, la case « + » suffit à dire qu'il n'y a rien : pas de phrase en plus. --}}
    @if ($this->images->isEmpty() && ! $single)
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Aucune image pour l’instant. Cliquez sur « + » pour ouvrir la bibliothèque, ou glissez-en une depuis elle.
        </p>
    @endif

    {{--
        Une vignette se glisse aussi vers le texte de la période, où elle écrit sa référence : elle porte la même donnée
        qu'une carte de la bibliothèque (clé, genre, voyage). Sans cela, SortableJS y mettrait le texte de
        l'élément — ses espaces et son numéro —, que l'éditeur déposerait tel quel, en autant de paragraphes. Le marqueur
        `-panel` dit à la case de dépôt que ce glisser vient d'ici : elle n'a rien à en faire. Le glisser montre une petite
        étiquette (« Image 3 ») plutôt que la vignette : on voit où l'on vise dans le texte (voir library-drag.js).
        Au dépôt, la page reste où elle était pendant qu'elle se redessine (`libraryHoldScroll`, même fichier).
    --}}
    <div
        @unless ($single)
            data-library-sortable
            x-sortable
            x-init="$nextTick(() => $el.sortable?.option('setData', (dataTransfer, dragEl) => {
                const items = [{ media: Number(dragEl.getAttribute('x-sortable-item')), kind: dragEl.dataset.libraryKind }]
                dataTransfer.setData(@js($this->dragType()), JSON.stringify({ orchestration: {{ $orchestrationId }}, items }))
                dataTransfer.setData(@js($this->dragType().'-panel'), '1')
                window.libraryDragGhost?.(dataTransfer, items)
            }))"
            x-on:start="window.libraryScrollAtDrag = window.scrollY"
            x-on:end.stop="window.libraryHoldScroll?.(window.libraryScrollAtDrag ?? window.scrollY); $wire.reorder($event.target.sortable.toArray())"
        @else
            data-library-cover-panel
        @endunless
        class="flex flex-wrap gap-2"
    >
        @foreach ($this->images as $media)
            @if ($single)
                <div
                    wire:key="tag-cover-{{ $media->getKey() }}"
                    data-library-cover="{{ $media->getKey() }}"
                    x-tooltip="{ content: @js($media->isPlayable() ? 'Image de une : elle sert de couverture' : 'Image de une : elle sert de couverture. Cliquer pour la cadrer'), theme: $store.theme }"
                    @unless ($media->isPlayable())
                        x-on:click="if (! $event.target.closest('button')) $wire.mountAction('focus', { media: {{ $media->getKey() }} })"
                    @endunless
                    class="group relative rounded-lg ring-2 ring-primary-500 ring-offset-2 dark:ring-offset-gray-900 {{ $media->isPlayable() ? '' : 'cursor-pointer' }}"
                >
                    @include('filament-orchestrator::livewire.partials.tile-media', ['media' => $media])

                    <span class="pointer-events-none absolute left-1 top-1 flex items-center rounded-full bg-primary-600/90 p-1 text-white shadow">
                        <x-filament::icon :icon="$this->headerIcon()" class="h-3 w-3" />
                    </span>

                    @include('filament-orchestrator::livewire.partials.tile-focus', ['media' => $media])

                    <button
                        type="button"
                        wire:click="mountAction('detach', { media: {{ $media->getKey() }} })"
                        class="absolute right-1 top-1 hidden rounded-full bg-white/90 p-0.5 text-gray-600 shadow hover:text-danger-600 group-hover:block dark:bg-gray-900/90 dark:text-gray-300"
                        aria-label="Retirer l’image de une"
                        title="Retirer (elle reste dans la bibliothèque)"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            @else
                <div
                    wire:key="tag-image-{{ $media->getKey() }}"
                    x-sortable-item="{{ $media->getKey() }}"
                    x-sortable-handle
                    data-library-image="{{ $media->getKey() }}"
                    data-library-position="{{ $this->positions[$media->getKey()] }}"
                    data-library-kind="{{ $media->kind() }}"
                    x-data="{ linked: false }"
                    x-on:mouseenter="$dispatch('{{ $this->hoverEvent() }}', { media: {{ $media->getKey() }}, on: true, source: 'panel' })"
                    x-on:mouseleave="$dispatch('{{ $this->hoverEvent() }}', { media: {{ $media->getKey() }}, on: false, source: 'panel' })"
                    x-on:{{ $this->hoverEvent() }}.window="if ($event.detail.media === {{ $media->getKey() }}) linked = $event.detail.on"
                    @unless ($media->isPlayable())
                        x-on:click="if (! $event.target.closest('button')) $wire.mountAction('focus', { media: {{ $media->getKey() }} })"
                    @endunless
                    x-bind:class="linked ? 'ring-2 ring-primary-500 ring-offset-2 dark:ring-offset-gray-900' : ''"
                    @if ($media->getKey() === $this->fallbackCoverId)
                        data-library-header
                        x-tooltip="{ content: 'Couverture par défaut, tant qu’aucune image de une n’est choisie', theme: $store.theme }"
                    @endif
                    class="group relative cursor-grab rounded-lg active:cursor-grabbing {{ $media->getKey() === $this->fallbackCoverId ? 'ring-2 ring-primary-500 ring-offset-2 dark:ring-offset-gray-900' : '' }}"
                >
                    @include('filament-orchestrator::livewire.partials.tile-media', ['media' => $media])

                    @if ($media->getKey() === $this->fallbackCoverId)
                        <span class="pointer-events-none absolute left-1 top-1 flex items-center rounded-full bg-primary-600/90 p-1 text-white shadow">
                            <x-filament::icon :icon="$this->headerIcon()" class="h-3 w-3" />
                        </span>
                    @endif

                    {{-- Le numéro de l'image dans le carnet : « (image N) » dans le texte ; « V1 » : la première vidéo. --}}
                    <span
                        data-library-number
                        class="pointer-events-none absolute bottom-1 left-1 min-w-5 rounded-full bg-black/60 px-1.5 text-center text-[10px] font-semibold leading-5 text-white shadow"
                    >{{ $media->isVideo() ? 'V' : '' }}{{ $this->positions[$media->getKey()] }}</span>

                    @include('filament-orchestrator::livewire.partials.tile-focus', ['media' => $media])

                    <button
                        type="button"
                        wire:click="mountAction('detach', { media: {{ $media->getKey() }} })"
                        class="absolute right-1 top-1 hidden rounded-full bg-white/90 p-0.5 text-gray-600 shadow hover:text-danger-600 group-hover:block dark:bg-gray-900/90 dark:text-gray-300"
                        aria-label="Retirer cette image"
                        title="Retirer (elle reste dans la bibliothèque)"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            @endif
        @endforeach

        {{-- Ni `x-sortable-item` : on ne la déplace pas, et les images se rangent avant elle. Un bouton : clic ou dépôt. --}}
        @if ($tags !== [])
            <button
                type="button"
                data-library-dropzone
                wire:click="mountAction('library')"
                aria-label="Ouvrir la bibliothèque"
                title="Ouvrir la bibliothèque, ou y glisser une image depuis elle"
                x-data="{ over: false }"
                x-on:dragover="if ($event.dataTransfer.types.includes(@js($this->dragType())) && ! $event.dataTransfer.types.includes(@js($this->dragType().'-panel'))) { $event.preventDefault(); $event.dataTransfer.dropEffect = 'copy'; over = true }"
                x-on:dragleave="if (! $el.contains($event.relatedTarget)) over = false"
                x-on:drop="
                    over = false
                    const raw = $event.dataTransfer.getData(@js($this->dragType()))
                    if (! raw) return
                    $event.preventDefault()
                    try {
                        const drag = JSON.parse(raw)
                        const ids = (drag.items ?? [drag]).map((item) => Number(item.media)).filter((id) => id > 0)
                        if (ids.length) $wire.attachMedia(ids)
                    } catch (e) {}
                "
                x-bind:class="over ? 'border-primary-500 bg-primary-50 text-primary-600 dark:bg-primary-500/10' : 'border-gray-300 text-gray-400 dark:border-white/20'"
                class="flex h-20 w-20 cursor-pointer flex-col items-center justify-center gap-0.5 rounded-lg border-2 border-dashed text-center text-[11px] font-medium leading-tight transition hover:border-primary-500 hover:text-primary-600"
            >
                <x-filament::icon icon="heroicon-m-plus" class="h-6 w-6" />
                {{ $single && $this->images->isNotEmpty() ? 'Remplacer' : 'Ajouter' }}
            </button>
        @endif
    </div>

    @php($framable = $this->images->contains(fn ($media): bool => ! $media->isPlayable()))
    @if ($framable || (! $single && $this->images->count() > 1))
        <p class="text-xs text-gray-500 dark:text-gray-400">
            @if ($framable)
                Cliquez sur une image pour choisir son cadrage.
            @endif
            @if (! $single && $this->images->count() > 1)
                Glissez pour réordonner, ou dans le texte pour y écrire une référence.
            @endif
            @if ($this->fallbackCoverId !== null)
                Sans image de une, la première fait la couverture.
            @endif
        </p>
    @endif

    <x-filament-actions::modals />
</div>
