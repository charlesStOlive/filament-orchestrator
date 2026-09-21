/*
 * La référence à une image de la bibliothèque, dans le RichEditor : « (image 3) ».
 *
 * Extension TipTap chargée par Filament à la demande (voir LibraryImagePlugin). Elle
 * réutilise l'instance de TipTap / ProseMirror de l'éditeur, exposée sur
 * `window.FilamentRichEditor.tiptap` : pas de build.
 *
 * - On y dépose une image glissée depuis la bibliothèque : la référence s'écrit là
 *   où on lâche, et la page en est avertie (événement Livewire `library-image-dropped`)
 *   pour rattacher l'image à son contenu.
 * - Le nœud ne garde que la clé de l'image. Son numéro est sa place parmi les
 *   vignettes du panneau d'images de la page (`[data-library-image]`, voir
 *   tag-images-panel) : on le relit à chaque changement du panneau, il suit donc les
 *   réordonnancements. Sans vignette correspondante, la référence est orpheline.
 * - Survoler une référence met en évidence l'image dans le panneau, et
 *   inversement : ils se le disent par l'événement `library-image-hover` (window).
 */

const { Node, mergeAttributes } = window.FilamentRichEditor.tiptap.core
const { Plugin, PluginKey } = window.FilamentRichEditor.tiptap.pmState

// Les mêmes noms que Library\LibraryImages::DRAG_TYPE et Library\LibraryImageEvent, côté PHP.
const DRAG_TYPE = 'application/x-orchestrator-library-image'
const DROPPED = 'library-image-dropped'
const HOVER = 'library-image-hover'

const NAME = 'libraryImage'

/** La place de l'image (à partir de 1) dans le panneau d'images de la page, ou null. */
const positionOf = (media) => {
    const tile = document.querySelector(`[data-library-image="${media}"]`)
    const position = Number(tile?.dataset.libraryPosition)

    return Number.isInteger(position) && position > 0 ? position : null
}

// Ce qui redessine le numéro de chaque référence de la page, dont celles des éditeurs ouverts.
const refreshers = new Set()
const refreshAll = () => refreshers.forEach((refresh) => refresh())

// Le panneau d'images est un composant Livewire : quand il change (ajout, retrait, réordonnancement),
// les numéros changent.
const watchLivewire = () => window.Livewire.hook('morphed', refreshAll)

if (window.Livewire) {
    watchLivewire()
} else {
    document.addEventListener('livewire:init', watchLivewire, { once: true })
}

// L'image survolée dans un panneau : ses références s'allument.
window.addEventListener(HOVER, (event) => {
    document
        .querySelectorAll(`.library-image-ref[data-media="${event.detail.media}"]`)
        .forEach((ref) => ref.classList.toggle('is-linked', event.detail.on))
})

const announceHover = (media, on) => {
    window.dispatchEvent(new CustomEvent(HOVER, { detail: { media: Number(media), on, source: 'editor' } }))
}

export default Node.create({
    name: NAME,

    group: 'inline',

    inline: true,

    atom: true,

    selectable: true,

    addOptions() {
        return { HTMLAttributes: {} }
    },

    addAttributes() {
        return {
            media: {
                default: null,
                parseHTML: (element) => {
                    const media = Number(element.getAttribute('data-media'))

                    return Number.isInteger(media) && media > 0 ? media : null
                },
                renderHTML: (attributes) => (attributes.media ? { 'data-media': attributes.media } : {}),
            },
        }
    },

    parseHTML() {
        return [{ tag: `span[data-type="${NAME}"]` }]
    },

    // Le HTML enregistré : un span vide qui ne porte que la clé. Le numéro serait périmé au premier
    // réordonnancement ; l'éditeur et le carnet le recalculent.
    renderHTML({ HTMLAttributes }) {
        return ['span', mergeAttributes({ 'data-type': NAME }, this.options.HTMLAttributes, HTMLAttributes)]
    },

    renderText() {
        return '(image)'
    },

    addNodeView() {
        return ({ node }) => {
            const media = String(node.attrs.media)
            const dom = document.createElement('span')

            dom.className = 'library-image-ref'
            dom.setAttribute('data-type', NAME)
            dom.setAttribute('data-media', media)
            dom.contentEditable = 'false'

            const refresh = () => {
                const position = positionOf(media)

                dom.textContent = position ? `(image ${position})` : '(image ?)'
                dom.classList.toggle('is-orphan', position === null)
                dom.title = position ? '' : 'Cette image n’est plus dans les images de la période'
            }

            const enter = () => announceHover(media, true)
            const leave = () => announceHover(media, false)

            refresh()
            refreshers.add(refresh)
            dom.addEventListener('mouseenter', enter)
            dom.addEventListener('mouseleave', leave)

            return {
                dom,
                update: (updated) => updated.type === node.type && String(updated.attrs.media) === media,
                destroy: () => {
                    refreshers.delete(refresh)
                    leave()
                },
            }
        }
    },

    addProseMirrorPlugins() {
        return [
            new Plugin({
                key: new PluginKey(`${NAME}Drop`),
                props: {
                    handleDrop: (view, event) => {
                        const raw = event.dataTransfer?.getData(DRAG_TYPE)

                        if (!raw) {
                            return false
                        }

                        let dragged

                        try {
                            dragged = JSON.parse(raw)
                        } catch {
                            return false
                        }

                        const media = Number(dragged?.media)
                        const at = view.posAtCoords({ left: event.clientX, top: event.clientY })
                        const type = view.state.schema.nodes[NAME]

                        if (!Number.isInteger(media) || media < 1 || !at || !type) {
                            return false
                        }

                        event.preventDefault()

                        // La référence, précédée d'une espace si elle suit un mot, et suivie d'une autre pour continuer à écrire.
                        const { schema, doc } = view.state
                        const previous = doc.resolve(at.pos).nodeBefore
                        const content = [type.create({ media }), schema.text(' ')]

                        // Au début d'un paragraphe il n'y a rien avant ; sinon, un mot ou une autre référence.
                        if (previous && !(previous.isText && /\s$/.test(previous.text))) {
                            content.unshift(schema.text(' '))
                        }

                        view.dispatch(view.state.tr.insert(at.pos, content))
                        view.focus()

                        // La page rattache l'image à son contenu, et le panneau se redessine.
                        window.Livewire?.dispatch(DROPPED, { media, orchestration: dragged.orchestration ?? null })

                        return true
                    },
                },
            }),
        ]
    },
})
