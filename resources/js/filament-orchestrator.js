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

window.addEventListener('filament-orchestrator:destroy', (event) => {
    manager.destroy(event.detail.id)
})

window.addEventListener('filament-map:point-clicked', (event) => {
    manager.pointClicked(event.detail)
})
