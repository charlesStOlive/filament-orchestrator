<div class="space-y-3">
    <div class="text-sm font-medium text-gray-950 dark:text-white">
        {{ $heading }}
        <span class="font-normal text-gray-500 dark:text-gray-400">({{ $this->images->count() }})</span>
    </div>

    {{--
        Glisser-déposer natif de Filament (x-sortable) : l'ordre est enregistré
        au dépôt. La première image est l'en-tête, accentuée.

        Chaque image porte sa clé (`data-library-image`, celle de la bibliothèque,
        qui survit aux changements d'ordre) et sa place (`data-library-position`,
        à partir de 1 : le numéro que le carnet lui donne). C'est par elles que le
        texte d'un contenu désigne une image (voir library-image.js). Le survol d'une
        vignette met en évidence ses références dans le texte, et inversement : les
        deux se le disent par l'événement navigateur « {{ $this->hoverEvent() }} ».

        À la suite de la dernière image, toujours, une case « + » : un clic ouvre la
        bibliothèque (c'est aussi là qu'on envoie des fichiers), et on peut y déposer une
        image glissée depuis la bibliothèque : elle s'ajoute à la fin. Elle ne réagit qu'à
        ces images-là (pas au réordonnancement d'ici, qui a ses propres événements).
    --}}
    @if ($this->images->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Aucune image pour l’instant. Cliquez sur « + » pour ouvrir la bibliothèque, ou glissez-en une depuis elle.
        </p>
    @endif

    {{--
        Une vignette se glisse aussi vers le texte de la période, où elle écrit sa référence : elle porte la même donnée
        qu'une carte de la bibliothèque (clé de l'image et du voyage). Sans cela, SortableJS y mettrait le texte de
        l'élément — ses espaces et son numéro —, que l'éditeur déposerait tel quel, en autant de paragraphes. Le marqueur
        `-panel` dit à la case de dépôt que ce glisser vient d'ici : elle n'a rien à en faire.
    --}}
    <div
        data-library-sortable
        x-sortable
        x-init="$nextTick(() => $el.sortable?.option('setData', (dataTransfer, dragEl) => {
            dataTransfer.setData(@js($this->dragType()), JSON.stringify({ media: Number(dragEl.getAttribute('x-sortable-item')), orchestration: {{ $orchestrationId }} }))
            dataTransfer.setData(@js($this->dragType().'-panel'), '1')
        }))"
        x-on:end.stop="$wire.reorder($event.target.sortable.toArray())"
        class="flex flex-wrap gap-2"
    >
        @foreach ($this->images as $media)
            <div
                wire:key="tag-image-{{ $media->getKey() }}"
                x-sortable-item="{{ $media->getKey() }}"
                x-sortable-handle
                data-library-image="{{ $media->getKey() }}"
                data-library-position="{{ $loop->iteration }}"
                x-data="{ linked: false }"
                x-on:mouseenter="$dispatch('{{ $this->hoverEvent() }}', { media: {{ $media->getKey() }}, on: true, source: 'panel' })"
                x-on:mouseleave="$dispatch('{{ $this->hoverEvent() }}', { media: {{ $media->getKey() }}, on: false, source: 'panel' })"
                x-on:{{ $this->hoverEvent() }}.window="if ($event.detail.media === {{ $media->getKey() }}) linked = $event.detail.on"
                x-bind:class="linked ? 'ring-2 ring-primary-500 ring-offset-2 dark:ring-offset-gray-900' : ''"
                @if ($loop->first)
                    data-library-header
                    x-tooltip="{ content: 'Image d’en-tête : la première', theme: $store.theme }"
                @endif
                class="group relative cursor-grab rounded-lg active:cursor-grabbing {{ $loop->first ? 'ring-2 ring-primary-500 ring-offset-2 dark:ring-offset-gray-900' : '' }}"
            >
                <img
                    src="{{ $media->thumbUrl() }}"
                    alt="{{ $media->name }}"
                    loading="lazy"
                    draggable="false"
                    class="h-20 w-20 rounded-lg object-cover"
                />

                @if ($loop->first)
                    <span class="pointer-events-none absolute left-1 top-1 flex items-center rounded-full bg-primary-600/90 p-1 text-white shadow">
                        <x-filament::icon :icon="$this->headerIcon()" class="h-3 w-3" />
                    </span>
                @endif

                {{-- Le numéro de l'image dans le carnet : « (image N) » dans le texte. --}}
                <span
                    data-library-number
                    class="pointer-events-none absolute bottom-1 left-1 min-w-5 rounded-full bg-black/60 px-1.5 text-center text-[10px] font-semibold leading-5 text-white shadow"
                >{{ $loop->iteration }}</span>

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
                    try { $wire.attachMedia(Number(JSON.parse(raw).media)) } catch (e) {}
                "
                x-bind:class="over ? 'border-primary-500 bg-primary-50 text-primary-600 dark:bg-primary-500/10' : 'border-gray-300 text-gray-400 dark:border-white/20'"
                class="flex h-20 w-20 cursor-pointer flex-col items-center justify-center gap-0.5 rounded-lg border-2 border-dashed text-center text-[11px] font-medium leading-tight transition hover:border-primary-500 hover:text-primary-600"
            >
                <x-filament::icon icon="heroicon-m-plus" class="h-6 w-6" />
                Ajouter
            </button>
        @endif
    </div>

    @if ($this->images->count() > 1)
        <p class="text-xs text-gray-500 dark:text-gray-400">
            Glissez pour réordonner : la première image est l’en-tête.
        </p>
    @endif

    <x-filament-actions::modals />
</div>
