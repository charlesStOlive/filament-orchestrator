export class OrchestratorInstance {
    constructor(element, payload) {
        this.element = element
        this.payload = payload
        this.contentElement = element.querySelector('[data-orchestrator-content]')
        this.closeButton = element.querySelector('[data-orchestrator-content-close]')
        this.handleClose = () => this.closeContent()
        this.closeButton?.addEventListener('click', this.handleClose)
    }

    update(payload) {
        this.payload = payload
    }

    destroy() {
        this.closeButton?.removeEventListener('click', this.handleClose)
    }

    pointClicked(detail) {
        if (!this.matchesMap(detail)) {
            return
        }

        const pointKey = String(detail.point?.id ?? '')
        const interactions = this.payload.interactions ?? []

        for (const interaction of interactions) {
            if (interaction.trigger?.type !== 'click' || interaction.source?.type !== 'map.point') {
                continue
            }

            if (interaction.source?.key && String(interaction.source.key) !== pointKey) {
                continue
            }

            this.execute(interaction.actions ?? [], detail)
        }
    }

    matchesMap(detail) {
        const experience = this.payload.experience ?? {}
        const sameMap = experience.mapId === null
            || experience.mapId === undefined
            || String(experience.mapId) === String(detail.mapId)
        const sameScope = !experience.scope || experience.scope === detail.scope

        return sameMap && sameScope
    }

    execute(actions, context) {
        for (const action of actions) {
            try {
                this.executeAction(action, context)
                this.emit('filament-orchestrator:action-executed', { action, context })
            } catch (error) {
                this.emit('filament-orchestrator:action-error', {
                    action,
                    context,
                    message: error?.message ?? 'Action impossible a executer.',
                })
            }
        }
    }

    executeAction(action, context) {
        if (action.type === 'content.open') {
            this.openContent(action.target)
            return
        }

        if (action.type === 'content.close') {
            this.closeContent()
            return
        }

        if (action.type.startsWith('map.layer.')) {
            this.mapLayerCommand(action, context)
            return
        }

        if (action.type === 'event.dispatch') {
            if (!action.target) {
                throw new Error('Le nom de l’evenement est manquant.')
            }

            this.emit(action.target, {
                ...action.payload,
                experience: this.payload.experience,
                source: context,
            }, true)
            return
        }

        if (action.type === 'navigation.open') {
            this.navigate(action)
            return
        }

        throw new Error(`Type d’action non pris en charge : ${action.type}`)
    }

    openContent(key) {
        const content = this.payload.contents?.[key]

        if (!content || !this.contentElement) {
            throw new Error(`Contenu introuvable : ${key}`)
        }

        this.element.querySelector('[data-orchestrator-content-title]').textContent = content.title ?? ''
        this.element.querySelector('[data-orchestrator-content-body]').textContent = content.body ?? ''

        const imagesElement = this.element.querySelector('[data-orchestrator-content-images]')
        imagesElement.replaceChildren()

        for (const source of content.images ?? []) {
            if (!this.safeResourceUrl(source)) {
                continue
            }

            const image = document.createElement('img')
            image.src = source
            image.alt = content.title ?? ''
            image.loading = 'lazy'
            image.className = 'h-auto w-full rounded-lg object-cover'
            imagesElement.appendChild(image)
        }

        this.contentElement.hidden = false
    }

    closeContent() {
        if (this.contentElement) {
            this.contentElement.hidden = true
        }
    }

    mapLayerCommand(action, context) {
        const operation = action.type.split('.').at(-1)

        this.emit('filament-map:command', {
            mapId: context.mapId,
            scope: context.scope,
            command: `${operation}-layer`,
            target: action.target,
            payload: action.payload ?? {},
        })
    }

    navigate(action) {
        const url = new URL(action.target, window.location.origin)

        if (!['http:', 'https:'].includes(url.protocol)) {
            throw new Error('Protocole de navigation refuse.')
        }

        if (action.options?.new_tab) {
            window.open(url.toString(), '_blank', 'noopener,noreferrer')
        } else {
            window.location.assign(url.toString())
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
