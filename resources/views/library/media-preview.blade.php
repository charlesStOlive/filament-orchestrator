{{--
    La partie droite (80 %) de la fenêtre d'édition : l'image entière, ou la vidéo avec son lecteur. Sur fond sombre, sans
    recadrage. Le serveur ne fait aucun aperçu d'une vidéo : le navigateur la lit, et en dit la durée et les dimensions.

    Pour une vidéo, la coupe (début et fin, en secondes) est non destructive : le lecteur s'en tient à ce passage. Les
    boutons y reportent l'endroit où l'on est arrivé, dans les champs du bandeau gauche (`data-trim`), qui restent
    modifiables au clavier. « Voir la coupe » joue le passage, de son début à sa fin.
--}}
@if ($media->isYoutube())
    {{-- Pas d'API YouTube : rien à mesurer ni à couper ici, seulement l'iframe officielle. --}}
    <div data-library-preview="youtube" class="flex h-full items-center justify-center overflow-hidden rounded-lg bg-black lg:sticky lg:top-0">
        <iframe
            src="{{ $media->youtubeEmbedUrl() }}"
            title="{{ $media->getCustomProperty('alt', $media->name) }}"
            loading="lazy"
            class="aspect-video max-h-[72vh] w-full border-0"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
            referrerpolicy="strict-origin-when-cross-origin"
            allowfullscreen
        ></iframe>
    </div>
@elseif ($media->isVideo())
    <div
        data-library-preview="video"
        class="flex h-full flex-col gap-3 lg:sticky lg:top-0"
        x-data="{
            duration: null,
            size: null,
            time: 0,
            playing: false,
            field(name) { return $root.closest('.fi-modal-window')?.querySelector(`[data-trim='${name}']`) },
            value(name) { const v = parseFloat(this.field(name)?.value); return Number.isFinite(v) ? v : null },
            set(name, v) {
                const input = this.field(name)
                if (! input) return
                input.value = v === null ? '' : (Math.round(v * 10) / 10).toString()
                input.dispatchEvent(new Event('input', { bubbles: true }))
            },
            clock(seconds) { seconds = Math.max(0, Math.floor(seconds ?? 0)); return Math.floor(seconds / 60) + ':' + String(seconds % 60).padStart(2, '0') },
            watchCut() {
                const end = this.value('trim_end')
                if (this.playing && end !== null && $refs.video.currentTime >= end) { $refs.video.pause(); this.playing = false }
            },
            playCut() {
                const start = this.value('trim_start') ?? 0
                $refs.video.currentTime = start
                this.playing = true
                $refs.video.play()
            },
        }"
    >
        <div class="flex min-h-0 flex-1 items-center justify-center overflow-hidden rounded-lg bg-black">
            <video
                x-ref="video"
                src="{{ $media->getUrl() }}"
                controls
                preload="metadata"
                playsinline
                x-on:loadedmetadata="duration = $el.duration; size = $el.videoWidth + ' × ' + $el.videoHeight"
                x-on:timeupdate="time = $el.currentTime; watchCut()"
                x-on:pause="playing = false"
                class="max-h-[68vh] w-full"
            ></video>
        </div>

        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-gray-600 dark:text-gray-300">
            <span data-library-video-facts>
                Durée <strong class="font-semibold text-gray-950 dark:text-white" x-text="duration === null ? '…' : clock(duration)"></strong>
                <span x-show="size" x-cloak> · <span x-text="size"></span></span>
            </span>

            <span class="hidden h-4 w-px bg-gray-300 sm:block dark:bg-white/20"></span>

            <span class="text-gray-500 dark:text-gray-400">Coupe (le fichier reste entier)</span>
            <button type="button" x-on:click="set('trim_start', $refs.video.currentTime)" class="rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-800 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-100 dark:hover:bg-white/20">
                Début ici (<span x-text="clock(time)"></span>)
            </button>
            <button type="button" x-on:click="set('trim_end', $refs.video.currentTime)" class="rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-800 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-100 dark:hover:bg-white/20">
                Fin ici (<span x-text="clock(time)"></span>)
            </button>
            <button type="button" x-on:click="playCut()" class="rounded-md bg-primary-600 px-2 py-1 text-xs font-medium text-white hover:bg-primary-500">
                Voir la coupe
            </button>
            <button type="button" x-on:click="set('trim_start', null); set('trim_end', null)" class="text-xs font-medium text-gray-500 underline hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-100">
                Effacer la coupe
            </button>
        </div>
    </div>
@else
    <div data-library-preview="image" class="flex h-full items-center justify-center overflow-hidden rounded-lg bg-gray-950 lg:sticky lg:top-0">
        <img
            src="{{ $media->conversionUrl('large') }}"
            alt="{{ $media->getCustomProperty('alt', $media->name) }}"
            class="max-h-[72vh] w-full object-contain"
        />
    </div>
@endif
