{{--
    Le choix du cadrage d'une image (TagImagesPanel::focusAction()) : une grille de 3 × 3, la même image dans chaque
    case carrée, recadrée de ce côté (`object-position`) et agrandie de 20 % vers lui (`transform-origin` au même
    point) — sans quoi une image presque carrée se ressemblerait d'une case à l'autre —, et la flèche qui pointe vers
    la partie gardée. La case choisie est cerclée ; « Appliquer » l'enregistre.
--}}
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{ state: $wire.$entangle(@js($getStatePath())) }"
        role="radiogroup"
        aria-label="Cadrage de l’image"
        class="grid grid-cols-3 gap-2"
    >
        @foreach ($focuses as $key => $focus)
            <button
                type="button"
                role="radio"
                data-focus="{{ $key }}"
                x-on:click="state = @js($key)"
                x-bind:aria-checked="state === @js($key)"
                x-bind:class="state === @js($key) ? 'ring-2 ring-primary-500 ring-offset-2 dark:ring-offset-gray-900' : 'opacity-75 hover:opacity-100'"
                x-tooltip="{ content: @js($focus['label']), theme: $store.theme }"
                class="relative aspect-square overflow-hidden rounded-lg bg-gray-100 transition dark:bg-gray-800"
            >
                <img
                    src="{{ $imageUrl }}"
                    alt=""
                    draggable="false"
                    class="h-full w-full scale-120 object-cover"
                    style="object-position: {{ $focus['x'] }}% {{ $focus['y'] }}%; transform-origin: {{ $focus['x'] }}% {{ $focus['y'] }}%"
                />

                <span class="pointer-events-none absolute inset-0 flex items-center justify-center">
                    <span
                        x-bind:class="state === @js($key) ? 'bg-primary-600' : 'bg-black/55'"
                        class="flex h-9 w-9 items-center justify-center rounded-full text-white shadow backdrop-blur-sm"
                    >
                        <x-filament::icon :icon="$focus['icon']" class="h-5 w-5" />
                    </span>
                </span>

                <span class="sr-only">{{ $focus['label'] }}</span>
            </button>
        @endforeach
    </div>
</x-dynamic-component>
