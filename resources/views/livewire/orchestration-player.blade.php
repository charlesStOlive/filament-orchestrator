<div
    id="{{ $playerDomId }}"
    class="space-y-4"
    data-filament-orchestrator
>
    @if ($showMap && $mapAvailable)
        @foreach ($mapNodes as $mapNode)
            @if ($mapNode->orchestratable_id)
                @livewire('filament-map-viewer', [
                    'map' => $mapNode->orchestratable_id,
                    'eventScope' => $orchestration->scope(),
                    'height' => $mapHeight,
                ], key($playerDomId.'-map-'.$mapNode->getKey()))
            @endif
        @endforeach
    @endif

    <section
        data-orchestrator-content
        hidden
        class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900"
    >
        <div class="flex items-start justify-between gap-4">
            <h2 data-orchestrator-content-title class="text-xl font-semibold text-gray-950 dark:text-white"></h2>
            <button
                type="button"
                data-orchestrator-content-close
                class="rounded-md px-2 py-1 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"
                aria-label="Fermer le contenu"
            >&times;</button>
        </div>
        <div data-orchestrator-content-images class="mt-4 grid gap-3 sm:grid-cols-2"></div>
        <div data-orchestrator-content-body class="mt-4 whitespace-pre-wrap text-gray-700 dark:text-gray-200"></div>
        <div data-orchestrator-content-buttons class="mt-5 flex flex-wrap gap-2"></div>
    </section>
</div>

@assets
    <script type="module" src="{{ asset('vendor/filament-orchestrator/filament-orchestrator.js') }}"></script>
@endassets

@script
    <script>
        const id = @js($playerDomId)
        const payload = @js($payload)

        window.__filamentOrchestratorPending = window.__filamentOrchestratorPending || {}
        window.__filamentOrchestratorPending[id] = payload
        window.dispatchEvent(new CustomEvent('filament-orchestrator:init', {
            detail: { id, payload },
        }))

        cleanup(() => {
            window.dispatchEvent(new CustomEvent('filament-orchestrator:destroy', {
                detail: { id },
            }))
        })
    </script>
@endscript
