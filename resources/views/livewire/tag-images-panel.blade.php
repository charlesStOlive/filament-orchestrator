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
        <div class="flex flex-wrap gap-2">
            @foreach ($this->images as $media)
                <div class="group relative" wire:key="tag-image-{{ $media->getKey() }}">
                    <img
                        src="{{ $media->thumbUrl() }}"
                        alt="{{ $media->name }}"
                        loading="lazy"
                        class="h-20 w-20 rounded-lg object-cover"
                    />

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
    @endif

    <x-filament-actions::modals />
</div>
