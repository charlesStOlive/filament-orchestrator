const mapCommand = (command) => ({ instance, action, event, target }) => {
    instance.mapCommand(command, target, action.parameters ?? {}, event)
}

export function registerBuiltinActions(registry) {
    registry
        .register('content.open', ({ instance, target, action }) => {
            instance.openContent(target ?? instance.resolveNode(action.target))
        })
        .register('content.hide', ({ instance }) => instance.hideContent())
        .register('content.next', ({ instance, action }) => instance.nextContent(action.parameters ?? {}))
        .register('map.zoomTo', mapCommand('zoom-to'))
        .register('map.moveTo', mapCommand('move-to'))
        .register('map.fitBounds', mapCommand('fit-bounds'))
        .register('map.layer.show', mapCommand('show-layer'))
        .register('map.layer.hide', mapCommand('hide-layer'))
        .register('map.layer.toggle', mapCommand('toggle-layer'))
        .register('map.highlightFeature', mapCommand('highlight-feature'))
        .register('event.dispatch', ({ instance, action, event }) => {
            const name = action.parameters?.event ?? action.target?.key

            if (!name) {
                throw new Error('Le nom de l’événement à émettre est manquant.')
            }

            instance.emitPublicEvent(name, action.parameters?.payload ?? {}, event)
        })
        .register('navigation.open', ({ instance, action }) => instance.navigate(action.parameters ?? {}))
}
