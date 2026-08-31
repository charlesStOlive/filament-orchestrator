import { OrchestratorManager } from './orchestrator-manager.js'

const manager = new OrchestratorManager()

window.FilamentOrchestrator = manager

for (const [id, payload] of Object.entries(window.__filamentOrchestratorPending ?? {})) {
    manager.init(id, payload)
    delete window.__filamentOrchestratorPending[id]
}

window.addEventListener('filament-orchestrator:init', (event) => {
    manager.init(event.detail.id, event.detail.payload)

    if (window.__filamentOrchestratorPending) {
        delete window.__filamentOrchestratorPending[event.detail.id]
    }
})

window.addEventListener('filament-orchestrator:destroy', (event) => manager.destroy(event.detail.id))

window.addEventListener('filament-orchestrator:event', (event) => manager.dispatch(event.detail))

window.addEventListener('filament-map:point-clicked', (event) => manager.dispatch({
    name: 'map.point.clicked',
    scope: event.detail.scope,
    source: {
        role: 'point',
        key: event.detail.point?.key ?? event.detail.point?.id,
        model: { id: event.detail.point?.id },
    },
    payload: event.detail,
}))

window.addEventListener('filament-map:feature-clicked', (event) => manager.dispatch({
    name: 'map.feature.clicked',
    scope: event.detail.scope,
    source: {
        role: 'layer',
        key: event.detail.layer?.key,
        model: { id: event.detail.layer?.id },
    },
    payload: event.detail,
}))

window.dispatchEvent(new CustomEvent('filament-orchestrator:ready', { detail: { manager } }))
