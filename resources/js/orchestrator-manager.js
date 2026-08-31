import { ActionRegistry } from './action-registry.js'
import { registerBuiltinActions } from './builtin-actions.js'
import { OrchestratorInstance } from './orchestrator-instance.js'

export class OrchestratorManager {
    constructor() {
        this.instances = new Map()
        this.actions = new ActionRegistry()
        registerBuiltinActions(this.actions)
    }

    init(id, payload) {
        const element = document.getElementById(id)

        if (!element) {
            return
        }

        if (this.instances.has(id)) {
            this.instances.get(id).update(payload)
            return
        }

        const instance = new OrchestratorInstance(
            element,
            payload,
            this.actions,
            (event) => this.dispatch(event),
        )
        this.instances.set(id, instance)

        queueMicrotask(() => this.dispatch({
            name: 'orchestration.initialized',
            orchestrationId: payload.orchestration?.id,
            scope: payload.orchestration?.scope,
            source: null,
            payload: {},
        }))
    }

    destroy(id) {
        this.instances.get(id)?.destroy()
        this.instances.delete(id)
    }

    dispatch(event) {
        const normalized = {
            name: event.name,
            orchestrationId: event.orchestrationId ?? null,
            scope: event.scope ?? null,
            source: event.source ?? null,
            payload: event.payload ?? {},
            meta: event.meta ?? {},
        }

        for (const instance of this.instances.values()) {
            instance.handleEvent(normalized)
        }
    }

    registerAction(name, handler) {
        this.actions.register(name, handler)
        return this
    }
}
