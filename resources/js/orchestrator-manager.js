import { OrchestratorInstance } from './orchestrator-instance.js'

export class OrchestratorManager {
    constructor() {
        this.instances = new Map()
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

        this.instances.set(id, new OrchestratorInstance(element, payload))
    }

    destroy(id) {
        this.instances.get(id)?.destroy()
        this.instances.delete(id)
    }

    pointClicked(detail) {
        for (const instance of this.instances.values()) {
            instance.pointClicked(detail)
        }
    }
}
