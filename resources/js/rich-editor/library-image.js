/*
 * La référence à des images ou des vidéos de la bibliothèque, dans le RichEditor.
 *
 * Extension TipTap chargée par Filament à la demande (voir LibraryImagePlugin). Elle
 * réutilise l'instance de TipTap / ProseMirror de l'éditeur, exposée sur
 * `window.FilamentRichEditor.tiptap` : pas de build.
 *
 * - On y dépose ce qu'on glisse depuis la bibliothèque (une ou plusieurs cartes cochées) ou
 *   depuis le panneau d'images : la référence s'écrit là où on lâche, comme un caractère, et
 *   la page en est avertie (événement Livewire `library-image-dropped`) pour rattacher les
 *   fichiers à son contenu. Il y a toujours une espace avant et après, jamais deux (voir
 *   `planSpacing`) ; celle d'avant est insécable, pour que la référence ne se retrouve pas
 *   seule en début de ligne.
 * - Une référence désigne des fichiers d'un même genre : « images 1, 2, 3 » ou « vidéo 1 ».
 *   Glisser des images et des vidéos ensemble écrit une référence par genre.
 * - Le nœud ne garde que les clés et le genre : ce sont elles qui font le lien, les numéros
 *   changent dès qu'on réordonne. Dans l'éditeur, la référence est un chip — l'icône du genre
 *   (photo ou lecture) et, pour chaque fichier, sa miniature (une image) et son numéro actuel,
 *   relus dans le panneau d'images de la page (`[data-library-image]`, voir tag-images-panel) à
 *   chaque changement de celui-ci. Les vidéos se numérotent à part des images. Le carnet écrit
 *   « (image N) » ou « (vidéos 1, 2, 3) » à sa place. Un fichier absent du panneau est orphelin.
 * - Survoler une référence met en évidence ses fichiers dans le panneau, et inversement : ils
 *   se le disent par l'événement `library-image-hover` (window).
 */

const { Node, mergeAttributes } = window.FilamentRichEditor.tiptap.core
const { Plugin, PluginKey } = window.FilamentRichEditor.tiptap.pmState

// Les mêmes noms que Library\LibraryImages::DRAG_TYPE et Library\LibraryImageEvent, côté PHP.
const DRAG_TYPE = 'application/x-orchestrator-library-image'
const DROPPED = 'library-image-dropped'
const HOVER = 'library-image-hover'

const NAME = 'libraryImage'

const NBSP = '\u00a0'

// Ce qui ne demande pas d'espace devant soi : la ponctuation qui ferme (« (image 2). », « (image 2), »).
const CLOSING = /^[.,;:!?…)\]}»”%]$/

/**
 * Les espaces à poser autour d'une référence qu'on dépose entre deux caractères.
 *
 * `previous` et `next` sont le caractère qui précède et celui qui suit le point de dépôt : `null` au bord du
 * paragraphe, `''` quand c'est autre chose que du texte (une autre référence).
 *
 * - Avant : l'espace qui précède devient insécable, ou une espace insécable s'ajoute s'il n'y en a pas.
 *   Rien au début d'un paragraphe.
 * - Après : une espace s'ajoute s'il n'y en a pas, sauf devant une ponctuation qui ferme et à la fin d'un
 *   paragraphe.
 *
 * @returns {{ replacePrevious: boolean, before: string, after: string }} `replacePrevious` : l'espace qui précède est
 *   remplacée par `before` au lieu de rester.
 */
export function planSpacing(previous, next) {
    let replacePrevious = false
    let before = ''
    let after = ''

    if (previous === ' ') {
        replacePrevious = true
        before = NBSP
    } else if (previous !== null && !/^\s$/.test(previous)) {
        before = NBSP
    }

    if (next !== null && !/^\s$/.test(next) && !CLOSING.test(next)) {
        after = ' '
    }

    return { replacePrevious, before, after }
}

/** La vignette du fichier dans le panneau d'images de la page, ou null. */
const tileOf = (media) => document.querySelector(`[data-library-image="${media}"]`)

/** La place du fichier (à partir de 1) dans son genre, dans le panneau d'images de la page, ou null. */
const positionOf = (media) => {
    const position = Number(tileOf(media)?.dataset.libraryPosition)

    return Number.isInteger(position) && position > 0 ? position : null
}

/** Des clés lues dans « 7,8,9 » : entiers strictement positifs, sans doublon, dans l'ordre. */
const idsOf = (value) => [
    ...new Set(
        String(value ?? '')
            .split(',')
            .map((part) => Number(part))
            .filter((id) => Number.isInteger(id) && id > 0),
    ),
]

const kindOf = (value) => (value === 'video' ? 'video' : 'image')

/**
 * Ce qu'un glisser de la bibliothèque transporte : `{ orchestration, items: [{ media, kind }] }` — une ou plusieurs
 * cartes. L'ancien format d'un seul fichier (`{ media, kind }`) est encore lu. Null si rien n'est exploitable.
 */
const parseDrag = (raw) => {
    let dragged

    try {
        dragged = JSON.parse(raw)
    } catch {
        return null
    }

    const source = Array.isArray(dragged?.items) ? dragged.items : dragged?.media ? [dragged] : []
    const seen = new Set()
    const items = []

    for (const item of source) {
        const media = Number(item?.media)

        if (Number.isInteger(media) && media > 0 && !seen.has(media)) {
            seen.add(media)
            items.push({ media, kind: kindOf(item?.kind) })
        }
    }

    return items.length ? { orchestration: dragged.orchestration ?? null, items } : null
}

// Les icônes du chip, dessinées à la taille du texte : « photo » (Heroicons, outline) pour les images, « lecture » (solide)
// pour les vidéos.
const ICONS = {
    image:
        '<svg class="library-image-ref__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" ' +
        'stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" ' +
        'd="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 ' +
        '2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 ' +
        '1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" /></svg>',
    video:
        '<svg class="library-image-ref__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" ' +
        'aria-hidden="true"><path d="M4.5 5.653c0-1.427 1.529-2.33 2.779-1.643l11.54 6.347c1.295.712 1.295 2.573 0 3.286L7.28 ' +
        '19.99c-1.25.687-2.779-.217-2.779-1.643V5.653Z" /></svg>',
}

const LABELS = { image: ['Image', 'Images'], video: ['Vidéo', 'Vidéos'] }

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

// Le fichier survolé dans un panneau : les références qui le citent s'allument.
window.addEventListener(HOVER, (event) => {
    document
        .querySelectorAll('.library-image-ref')
        .forEach((ref) => idsOf(ref.dataset.media).includes(event.detail.media) && ref.classList.toggle('is-linked', event.detail.on))
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
            // Les clés des fichiers désignés, dans l'ordre : « 7,8,9 » dans le HTML enregistré.
            media: {
                default: [],
                parseHTML: (element) => idsOf(element.getAttribute('data-media')),
                renderHTML: (attributes) => (attributes.media?.length ? { 'data-media': attributes.media.join(',') } : {}),
            },
            // 'image' ou 'video' : le carnet dit « image 2 » ou « vidéo 1 », et numérote chaque genre à part.
            kind: {
                default: 'image',
                parseHTML: (element) => kindOf(element.getAttribute('data-kind')),
                renderHTML: (attributes) => ({ 'data-kind': kindOf(attributes.kind) }),
            },
        }
    },

    parseHTML() {
        return [{ tag: `span[data-type="${NAME}"]` }]
    },

    // Le HTML enregistré : un span vide qui ne porte que les clés et le genre. Les numéros seraient périmés au premier
    // réordonnancement ; l'éditeur et le carnet les recalculent.
    renderHTML({ HTMLAttributes }) {
        return ['span', mergeAttributes({ 'data-type': NAME }, this.options.HTMLAttributes, HTMLAttributes)]
    },

    renderText({ node }) {
        return `(${LABELS[kindOf(node.attrs.kind)][0].toLowerCase()})`
    },

    addNodeView() {
        return ({ node }) => {
            const ids = idsOf((node.attrs.media ?? []).join(','))
            const kind = kindOf(node.attrs.kind)
            const dom = document.createElement('span')

            dom.className = `library-image-ref is-${kind}`
            dom.setAttribute('data-type', NAME)
            dom.setAttribute('data-media', ids.join(','))
            dom.setAttribute('data-kind', kind)
            dom.contentEditable = 'false'
            // Un chip : l'icône du genre, puis pour chaque fichier sa miniature (une image, quand on la connaît et qu'il n'y en
            // a pas trop) et son numéro actuel. Chaque pièce a sa taille bornée dans library-image.css — une image ou un SVG
            // sans taille s'étalerait sur toute la ligne.
            dom.innerHTML = `${ICONS[kind]}<span class="library-image-ref__items"></span>`

            const list = dom.querySelector('.library-image-ref__items')

            const refresh = () => {
                const positions = ids.map((id) => positionOf(id))
                const showThumbs = kind === 'image' && ids.length <= 2

                list.replaceChildren(
                    ...ids.map((id, index) => {
                        const item = document.createElement('span')
                        const source = tileOf(id)?.querySelector('img')?.getAttribute('src')

                        item.className = 'library-image-ref__item'
                        item.classList.toggle('is-orphan', positions[index] === null)

                        if (showThumbs && source) {
                            const thumb = document.createElement('img')

                            thumb.className = 'library-image-ref__thumb'
                            thumb.alt = ''
                            thumb.src = source
                            item.append(thumb)
                        }

                        const number = document.createElement('span')

                        number.className = 'library-image-ref__number'
                        number.textContent = positions[index] ?? '?'
                        item.append(number)

                        return item
                    }),
                )

                const label = LABELS[kind][ids.length > 1 ? 1 : 0]
                const known = positions.filter((position) => position !== null)

                dom.classList.toggle('is-orphan', known.length === 0)
                dom.title =
                    known.length === ids.length
                        ? `${label} n° ${known.join(', ')} de la période (clés ${ids.join(', ')}) : elles restent liées si on les réordonne`
                        : `${label} ${ids.join(', ')} : ${known.length === 0 ? 'plus dans' : 'en partie hors de'} la période`
            }

            const enter = () => ids.forEach((id) => announceHover(id, true))
            const leave = () => ids.forEach((id) => announceHover(id, false))

            refresh()
            refreshers.add(refresh)
            dom.addEventListener('mouseenter', enter)
            dom.addEventListener('mouseleave', leave)

            return {
                dom,
                update: (updated) =>
                    updated.type === node.type &&
                    kindOf(updated.attrs.kind) === kind &&
                    (updated.attrs.media ?? []).join(',') === ids.join(','),
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

                        const dragged = parseDrag(raw)
                        const at = view.posAtCoords({ left: event.clientX, top: event.clientY })
                        const type = view.state.schema.nodes[NAME]

                        if (!dragged || !at || !type) {
                            return false
                        }

                        event.preventDefault()

                        // Une référence par genre, dans l'ordre où les genres apparaissent : « images 1, 2 » puis « vidéo 1 ».
                        const groups = []

                        for (const { media, kind } of dragged.items) {
                            let group = groups.find((candidate) => candidate.kind === kind)

                            if (!group) {
                                group = { kind, media: [] }
                                groups.push(group)
                            }

                            group.media.push(media)
                        }

                        // Les références, séparées d'une espace, avec une espace de chaque côté quand il en manque (voir planSpacing).
                        const { schema, doc } = view.state
                        const $at = doc.resolve(at.pos)
                        const edge = (node, pick) => (node ? (node.isText ? pick(node.text) : '') : null)
                        const plan = planSpacing(
                            edge($at.nodeBefore, (text) => text.slice(-1)),
                            edge($at.nodeAfter, (text) => text.charAt(0)),
                        )
                        const content = []

                        groups.forEach((group, index) => {
                            if (index > 0) {
                                content.push(schema.text(' '))
                            }

                            content.push(type.create({ media: group.media, kind: group.kind }))
                        })

                        if (plan.before) {
                            content.unshift(schema.text(plan.before))
                        }

                        if (plan.after) {
                            content.push(schema.text(plan.after))
                        }

                        view.dispatch(view.state.tr.replaceWith(plan.replacePrevious ? at.pos - 1 : at.pos, at.pos, content))
                        view.focus()

                        // La page rattache les fichiers à son contenu, dans cet ordre, et le panneau se redessine.
                        window.Livewire?.dispatch(DROPPED, {
                            media: dragged.items.map((item) => item.media),
                            orchestration: dragged.orchestration,
                        })

                        return true
                    },
                },
            }),
        ]
    },
})
