export class ActionRegistry {
    constructor() {
        this.handlers = new Map()
    }

    register(name, handler) {
        if (!name || typeof handler !== 'function') {
            throw new Error('Une action requiert un nom et une fonction.')
        }

        this.handlers.set(name, handler)
        return this
    }

    unregister(name) {
        this.handlers.delete(name)
        return this
    }

    has(name) {
        return this.handlers.has(name)
    }

    async execute(name, context) {
        const handler = this.handlers.get(name)

        if (!handler) {
            throw new Error(`Aucun exécuteur JavaScript enregistré pour l’action : ${name}`)
        }

        return handler(context)
    }
}
