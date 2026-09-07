// Scene feature events target the scene node. Layers are never independent scenario nodes.
window.addEventListener('filament-map:feature-clicked', (event) => {
    const detail = event.detail
    const manager = window.FilamentOrchestrator
    if (!manager) return
    const instances = [...manager.instances.values()].filter((instance) =>
        instance.payload.orchestration?.scope === detail.scope
        && instance.payload.nodes.some((node) => node.data?.mapScene === true && String(node.model?.id) === String(detail.mapId)))
    if (!instances.length) return

    event.stopImmediatePropagation()
    for (const instance of instances) {
        const source = instance.payload.nodes.find((node) => node.data?.mapScene === true && String(node.model?.id) === String(detail.mapId))
        manager.dispatch({
            name: 'map.feature.clicked',
            orchestrationId: instance.payload.orchestration.id,
            scope: detail.scope,
            source,
            payload: detail,
        })
    }
}, true)
