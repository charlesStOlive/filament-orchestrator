<div class="space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="text-sm font-medium text-gray-950 dark:text-white">
            {{ $heading }}
            <span class="font-normal text-gray-500 dark:text-gray-400">({{ $this->images->count() }})</span>
        </div>

        <div class="flex flex-wrap gap-2">
            {{ $this->uploadAction }}
            {{ $this->libraryAction }}
        </div>
    </div>

    @if ($this->images->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Aucune image pour l’instant. Envoyez-en, ou rattachez-en depuis la bibliothèque.
        </p>
    @else
        {{--
            Glisser-déposer natif de Filament (x-sortable) : l'ordre est enregistré
            au dépôt. La première image est l'en-tête, accentuée.
        --}}
        <div
            data-library-sortable
            x-sortable
            x-on:end.stop="$wire.reorder($event.target.sortable.toArray())"
            class="flex flex-wrap gap-2"
        >
            @foreach ($this->images as $media)
                <div
                    wire:key="tag-image-{{ $media->getKey() }}"
                    x-sortable-item="{{ $media->getKey() }}"
                    x-sortable-handle
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
        </div>

        @if ($this->images->count() > 1)
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Glissez pour réordonner : la première image est l’en-tête.
            </p>
        @endif
    @endif

    <x-filament-actions::modals />
</div>
