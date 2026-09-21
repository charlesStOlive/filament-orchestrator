/*
 * La référence à une image de la bibliothèque, dans le RichEditor.
 *
 * Extension TipTap chargée par Filament à la demande (voir LibraryImagePlugin). Elle
 * réutilise l'instance de TipTap / ProseMirror de l'éditeur, exposée sur
 * `window.FilamentRichEditor.tiptap` : pas de build.
 *
 * - On y dépose une image glissée depuis la bibliothèque : la référence s'écrit là
 *   où on lâche, comme un caractère (aucune espace n'est ajoutée : c'est à l'auteur
 *   de les écrire), et la page en est avertie (événement Livewire
 *   `library-image-dropped`) pour rattacher l'image à son contenu.
 * - Le nœud ne garde que la clé de l'image : c'est elle qui fait le lien, le numéro
 *   change dès qu'on réordonne. Dans l'éditeur, la référence est un chip — l'icône
 *   d'une photo, sa miniature et son numéro actuel, relus dans le panneau d'images de
 *   la page (`[data-library-image]`, voir tag-images-panel) à chaque changement de
 *   celui-ci. Le carnet écrit « (image N) » à sa place. Sans vignette correspondante,
 *   la référence est orpheline.
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

/** La vignette de l'image dans le panneau d'images de la page, ou null. */
const tileOf = (media) => document.querySelector(`[data-library-image="${media}"]`)

/** La place de l'image (à partir de 1) dans le panneau d'images de la page, ou null. */
const positionOf = (media) => {
    const position = Number(tileOf(media)?.dataset.libraryPosition)

    return Number.isInteger(position) && position > 0 ? position : null
}

// L'icône « photo » (Heroicons, outline), dessinée à la taille du texte.
const ICON =
    '<svg class="library-image-ref__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" ' +
    'stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" ' +
    'd="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 ' +
    '2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 ' +
    '1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" /></svg>'

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
            // Un chip : l'icône, la miniature quand on la connaît, le numéro actuel. Chaque pièce a sa taille bornée
            // dans library-image.css — une image ou un SVG sans taille s'étalerait sur toute la ligne.
            dom.innerHTML = `${ICON}<img class="library-image-ref__thumb" alt="" hidden><span class="library-image-ref__number"></span>`

            const thumb = dom.querySelector('.library-image-ref__thumb')
            const number = dom.querySelector('.library-image-ref__number')

            const refresh = () => {
                const position = positionOf(media)
                const source = tileOf(media)?.querySelector('img')?.getAttribute('src')

                number.textContent = position ?? '?'
                dom.classList.toggle('is-orphan', position === null)
                dom.title = position
                    ? `Image n° ${position} de la période (clé ${media}) : elle reste liée à cette image si on les réordonne`
                    : `Image ${media} : elle n’est plus dans les images de la période`

                thumb.hidden = !source

                if (source && thumb.getAttribute('src') !== source) {
                    thumb.setAttribute('src', source)
                }
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

                        // La référence seule, comme un caractère : ni espace avant, ni espace après.
                        view.dispatch(view.state.tr.insert(at.pos, type.create({ media })))
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
