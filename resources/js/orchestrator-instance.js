export class OrchestratorInstance {
    constructor(element, payload, actions, dispatchEvent) {
        this.element = element
        this.payload = payload
        this.actions = actions
        this.dispatchEvent = dispatchEvent
        this.state = { ...(payload.orchestration?.initialState ?? {}) }
        this.contentHistory = []
        this.contentElement = element.querySelector('[data-orchestrator-content]')
        this.closeButton = element.querySelector('[data-orchestrator-content-close]')
        this.handleClose = () => this.hideContent(true)
        this.closeButton?.addEventListener('click', this.handleClose)
    }

    update(payload) {
        this.payload = payload
        this.state = { ...(payload.orchestration?.initialState ?? {}), ...this.state }
    }

    destroy() {
        this.closeButton?.removeEventListener('click', this.handleClose)
    }

    async handleEvent(event) {
        if (!this.acceptsEvent(event)) {
            return
        }

        const source = this.resolveEventSource(event.source)
        const normalizedEvent = { ...event, source }

        for (const trigger of this.payload.triggers ?? []) {
            if (!this.matchesTrigger(trigger, normalizedEvent)) {
                continue
            }

            await this.execute(trigger.actions ?? [], normalizedEvent, trigger)
        }
    }

    acceptsEvent(event) {
        const orchestration = this.payload.orchestration ?? {}
        const orchestrationMatches = !event.orchestrationId
            || String(event.orchestrationId) === String(orchestration.id)
        const scopeMatches = !event.scope || !orchestration.scope || event.scope === orchestration.scope

        return orchestrationMatches && scopeMatches
    }

    resolveEventSource(source = {}) {
        source ??= {}
        const nodes = this.payload.nodes ?? []
        const node = nodes.find((candidate) => {
            if (source.nodeId && String(candidate.id) === String(source.nodeId)) {
                return true
            }

            if (source.model?.id && String(candidate.model?.id) === String(source.model.id)) {
                return !source.role || candidate.role === source.role
            }

            return source.role && source.key
                && candidate.role === source.role
                && String(candidate.key) === String(source.key)
        })

        return node ? { ...source, ...node, raw: source } : source
    }

    matchesTrigger(trigger, event) {
        if (trigger.event !== event.name) {
            return false
        }

        if (trigger.source?.nodeId && String(trigger.source.nodeId) !== String(event.source?.id ?? '')) {
            return false
        }

        if (trigger.source?.role && trigger.source.role !== event.source?.role) {
            return false
        }

        if (trigger.source?.key && String(trigger.source.key) !== String(event.source?.key ?? '')) {
            return false
        }

        return this.matchesConditions(trigger.conditions ?? {}, event)
    }

    matchesConditions(conditions, event) {
        return Object.entries(conditions).every(([path, expected]) => {
            const actual = path.split('.').reduce((value, key) => value?.[key], event.payload ?? {})
            return Array.isArray(expected)
                ? expected.some((candidate) => this.valuesMatch(actual, candidate))
                : this.valuesMatch(actual, expected)
        })
    }

    valuesMatch(actual, expected) {
        if (actual === expected) {
            return true
        }

        const scalar = (value) => ['string', 'number', 'boolean'].includes(typeof value)

        return scalar(actual) && scalar(expected) && String(actual) === String(expected)
    }

    async execute(actions, event, trigger) {
        for (const action of actions) {
            try {
                const target = this.resolveNode(action.target, action.targetNodeId)
                await this.actions.execute(action.handler ?? action.name, {
                    instance: this,
                    action,
                    event,
                    trigger,
                    target,
                    state: this.state,
                    services: this.services(),
                })
                this.emit('filament-orchestrator:action-executed', { action, event, trigger })
            } catch (error) {
                this.emit('filament-orchestrator:action-error', {
                    action,
                    event,
                    trigger,
                    message: error?.message ?? 'Action impossible à exécuter.',
                })

                if (action.onError === 'stop') {
                    break
                }
            }
        }
    }

    resolveNode(target = {}, targetNodeId = null) {
        return (this.payload.nodes ?? []).find((node) => {
            if (targetNodeId && String(node.id) === String(targetNodeId)) {
                return true
            }

            return target?.role && target?.key
                && node.role === target.role
                && String(node.key) === String(target.key)
        }) ?? null
    }

    openContent(node) {
        if (!node || node.role !== 'content') {
            throw new Error('Le contenu ciblé est introuvable.')
        }

        // Un front qui dessine le contenu à sa façon annule cet événement
        // (`event.preventDefault()`) : le rendu par défaut ci-dessous n'a alors
        // pas lieu. Sans écouteur, ou sans rien annuler, rien ne change.
        const proceed = window.dispatchEvent(new CustomEvent('filament-orchestrator:content-opened', {
            cancelable: true,
            detail: {
                orchestrationId: this.payload.orchestration?.id,
                scope: this.payload.orchestration?.scope,
                node,
                instance: this,
            },
        }))

        if (proceed && this.contentElement) {
            this.renderContent(node)
        }

        if (this.state.activeContentNodeId && String(this.state.activeContentNodeId) !== String(node.id)) {
            this.contentHistory.push(this.state.activeContentNodeId)
        }

        this.state.activeContentNodeId = node.id

        if (proceed && this.contentElement) {
            this.contentElement.hidden = false
        }

        this.dispatchEvent({
            name: 'content.initialized',
            orchestrationId: this.payload.orchestration?.id,
            scope: this.payload.orchestration?.scope,
            source: node,
            payload: {},
        })
    }

    renderContent(node) {
        const content = node.data ?? {}
        this.element.querySelector('[data-orchestrator-content-title]').textContent = content.title ?? content.name ?? ''
        this.element.querySelector('[data-orchestrator-content-body]').textContent = content.body ?? ''

        const imagesElement = this.element.querySelector('[data-orchestrator-content-images]')
        imagesElement.replaceChildren()

        for (const imageData of content.images ?? []) {
            const source = typeof imageData === 'string' ? imageData : imageData.url

            if (!this.safeResourceUrl(source)) {
                continue
            }

            const image = document.createElement('img')
            image.src = source
            image.alt = typeof imageData === 'string' ? (content.title ?? '') : (imageData.alt ?? content.title ?? '')
            image.loading = 'lazy'
            image.className = 'h-auto w-full rounded-lg object-cover'
            imagesElement.appendChild(image)
        }

        const buttonsElement = this.element.querySelector('[data-orchestrator-content-buttons]')
        buttonsElement.replaceChildren()

        for (const buttonData of content.buttons ?? []) {
            const button = document.createElement('button')
            button.type = 'button'
            button.textContent = buttonData.label ?? buttonData.key
            button.className = 'rounded-md bg-primary-600 px-3 py-2 text-sm font-medium text-white'
            button.addEventListener('click', () => this.dispatchEvent({
                name: 'content.button.clicked',
                orchestrationId: this.payload.orchestration?.id,
                scope: this.payload.orchestration?.scope,
                source: node,
                payload: { button: buttonData },
            }))
            buttonsElement.appendChild(button)
        }
    }

    hideContent(userInitiated = false) {
        if (this.contentElement) {
            this.contentElement.hidden = true
        }

        const source = this.resolveNode({}, this.state.activeContentNodeId)
        this.state.activeContentNodeId = null

        if (userInitiated && source) {
            this.dispatchEvent({
                name: 'content.hidden',
                orchestrationId: this.payload.orchestration?.id,
                scope: this.payload.orchestration?.scope,
                source,
                payload: {},
            })
        }
    }

    nextContent(parameters = {}) {
        const contents = (this.payload.nodes ?? []).filter((node) => node.role === 'content')
        const sequence = Array.isArray(parameters.sequence)
            ? parameters.sequence.map((key) => contents.find((node) => node.key === key)).filter(Boolean)
            : contents

        if (!sequence.length) {
            throw new Error('Aucun contenu n’est disponible dans la séquence.')
        }

        const currentIndex = sequence.findIndex((node) => String(node.id) === String(this.state.activeContentNodeId))
        this.openContent(sequence[(currentIndex + 1) % sequence.length])
    }

    mapCommand(command, target, parameters, event) {
        const mapNodes = (this.payload.nodes ?? []).filter((node) => node.role === 'map')
        const targetedMap = target?.role === 'map' ? target : null
        const explicitMap = parameters.mapKey
            ? this.resolveNode({ role: 'map', key: parameters.mapKey })
            : null
        const eventMapId = event?.payload?.mapId ?? null
        const fallbackMap = mapNodes.length === 1 ? mapNodes[0] : null
        const mapId = targetedMap?.model?.id
            ?? explicitMap?.model?.id
            ?? eventMapId
            ?? fallbackMap?.model?.id

        if (mapId === null || mapId === undefined) {
            throw new Error('La carte cible est ambiguë. Renseignez le paramètre mapKey.')
        }

        this.emit('filament-map:command', {
            mapId,
            scope: this.payload.orchestration?.scope,
            command,
            target: parameters.layerKey ?? parameters.target ?? target?.key,
            payload: parameters,
        })
    }

    emitPublicEvent(name, payload, parentEvent) {
        const detail = {
            name,
            orchestrationId: this.payload.orchestration?.id,
            scope: this.payload.orchestration?.scope,
            source: parentEvent?.source ?? null,
            payload,
            meta: { parentEvent: parentEvent?.name ?? null },
        }

        this.emit(name, detail, true)
        this.emit('filament-orchestrator:event', detail)
    }

    navigate(parameters) {
        const url = new URL(parameters.url, window.location.origin)

        if (!['http:', 'https:'].includes(url.protocol)) {
            throw new Error('Protocole de navigation refusé.')
        }

        if (parameters.newTab) {
            window.open(url.toString(), '_blank', 'noopener,noreferrer')
        } else {
            window.location.assign(url.toString())
        }
    }

    services() {
        return {
            emit: (name, detail, livewire = false) => this.emit(name, detail, livewire),
            dispatchEvent: (event) => this.dispatchEvent(event),
            mapCommand: (command, target, parameters, event) => this.mapCommand(command, target, parameters, event),
            openContent: (node) => this.openContent(node),
            hideContent: () => this.hideContent(),
        }
    }

    safeResourceUrl(source) {
        try {
            const url = new URL(source, window.location.origin)
            return ['http:', 'https:'].includes(url.protocol)
        } catch {
            return false
        }
    }

    emit(name, detail, livewire = false) {
        window.dispatchEvent(new CustomEvent(name, { detail }))

        if (livewire) {
            window.Livewire?.dispatch?.(name, detail)
        }
    }
}
