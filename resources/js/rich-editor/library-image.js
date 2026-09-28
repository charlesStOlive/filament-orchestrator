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
 * - Pendant le glisser, un repère épais marque l'endroit où la référence s'écrira. Laissé un
 *   moment au milieu d'un mot (`WORD_DELAY`), le glisser englobe le mot : lâché là, le mot
 *   devient la référence (`label`), et le carnet l'écrira, cliquable, au lieu de « (image 3) ».
 * - Une référence désigne des fichiers d'un même genre : « images 1, 2, 3 » ou « vidéo 1 ».
 *   Glisser des images et des vidéos ensemble écrit une référence par genre.
 * - Le nœud ne garde que les clés et le genre : ce sont elles qui font le lien, les numéros
 *   changent dès qu'on réordonne. Dans l'éditeur, la référence est un chip — l'icône du genre
 *   (photo ou lecture), le mot englobé s'il y en a un et, pour chaque fichier, sa miniature (une
 *   image) et son numéro actuel, relus dans le panneau d'images de la page (`[data-library-image]`,
 *   voir tag-images-panel) à chaque changement de celui-ci. Les vidéos se numérotent à part des
 *   images. Le carnet écrit « (image N) » ou « (vidéos 1, 2, 3) » à sa place. Un fichier absent
 *   du panneau est orphelin.
 * - Un clic sur le chip ouvre une petite fenêtre : son titre (le carnet le montre au survol ; le
 *   chip porte alors une icône de bulle), le mot englobé, et de quoi supprimer la référence — le
 *   mot englobé, lui, reste dans le texte.
 * - Survoler une référence met en évidence ses fichiers dans le panneau, et inversement : ils
 *   se le disent par l'événement `library-image-hover` (window).
 */

const { Node, mergeAttributes } = window.FilamentRichEditor.tiptap.core
const { Plugin, PluginKey } = window.FilamentRichEditor.tiptap.pmState
const { Decoration, DecorationSet } = window.FilamentRichEditor.tiptap.pmView

// Les mêmes noms que Library\LibraryImages::DRAG_TYPE et Library\LibraryImageEvent, côté PHP.
const DRAG_TYPE = 'application/x-orchestrator-library-image'
const DROPPED = 'library-image-dropped'
const HOVER = 'library-image-hover'

const NAME = 'libraryImage'

const NBSP = ' '

// Ce qui ne demande pas d'espace devant soi : la ponctuation qui ferme (« (image 2). », « (image 2), »).
const CLOSING = /^[.,;:!?…)\]}»”%]$/

// Le temps qu'il faut rester au milieu d'un mot, en glissant, pour qu'il soit englobé (ms).
const WORD_DELAY = 700

// Une lettre (accents compris), un chiffre, ou un trait d'union : « arc-en-ciel » est un mot, « l'église » en a deux.
const WORD_CHAR = /^[\p{L}\p{M}\p{N}-]$/u

// Ce qui tient la place d'un nœud (une autre référence, un saut de ligne) dans le texte d'un paragraphe : pas une lettre.
const LEAF = '￼'

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

const isWordChar = (char) => char !== undefined && WORD_CHAR.test(char)

/**
 * Le mot dont `pos` est au milieu — une lettre de chaque côté —, ou null (au bord d'un mot, entre deux mots, hors du
 * texte). Les traits d'union du bord ne font pas partie du mot.
 *
 * @returns {{ from: number, to: number, text: string } | null}
 */
export function wordAt(doc, pos) {
    const $pos = doc.resolve(pos)

    if (!$pos.parent.inlineContent) {
        return null
    }

    // Chaque caractère, et chaque nœud (une référence, un saut de ligne), compte pour une position : l'indice dans ce
    // texte est la position dans le paragraphe.
    const text = $pos.parent.textBetween(0, $pos.parent.content.size, undefined, LEAF)
    const offset = $pos.parentOffset

    if (!isWordChar(text[offset - 1]) || !isWordChar(text[offset])) {
        return null
    }

    let start = offset
    let end = offset

    while (start > 0 && isWordChar(text[start - 1])) {
        start--
    }

    while (end < text.length && isWordChar(text[end])) {
        end++
    }

    while (start < end && text[start] === '-') {
        start++
    }

    while (end > start && text[end - 1] === '-') {
        end--
    }

    if (offset <= start || offset >= end) {
        return null
    }

    return { from: $pos.start() + start, to: $pos.start() + end, text: text.slice(start, end) }
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

/** Un texte facultatif (mot englobé, titre) : sur une ligne, sans espaces aux bords ; null s'il est vide. */
const textOf = (value) => {
    const text = String(value ?? '')
        .replace(/\s+/g, ' ')
        .trim()

    return text === '' ? null : text
}

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
// pour les vidéos, « bulle » (outline) quand la référence a un titre.
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
    title:
        '<svg class="library-image-ref__icon library-image-ref__title-icon" xmlns="http://www.w3.org/2000/svg" ' +
        'viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path ' +
        'stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 ' +
        '1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 ' +
        '3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 ' +
        '0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" /></svg>',
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

/*
 * La petite fenêtre d'une référence, ouverte par un clic sur son chip. Une seule à la fois dans la page.
 *
 * Elle se place sous le chip, dans le cadre du texte de l'éditeur (`.fi-fo-rich-editor-content`, positionné, et que
 * Livewire ne redessine pas) : dans une modale de Filament, qui retient le focus chez elle, ses champs restent
 * utilisables. Chaque frappe s'écrit aussitôt dans le nœud ; Entrée ou Échap la ferment, comme un clic ailleurs.
 */
let popover = null

const closePopover = () => popover?.close()

function openPopover({ editor, getPos, anchor, current }) {
    closePopover()

    const host = editor.view.dom.parentElement
    const el = document.createElement('div')
    const label = textOf(current().attrs.label)

    el.className = 'library-image-popover fi-not-prose'
    el.setAttribute('role', 'dialog')
    el.setAttribute('aria-label', 'Référence')
    el.innerHTML = `
        <label class="library-image-popover__field">
            <span>Titre</span>
            <input type="text" data-field="title" placeholder="Montré au survol, dans le carnet" autocomplete="off" />
        </label>
        ${
            label
                ? `<label class="library-image-popover__field">
                       <span>Mot du texte</span>
                       <input type="text" data-field="label" autocomplete="off" />
                   </label>`
                : ''
        }
        <div class="library-image-popover__actions">
            <button type="button" data-action="delete" class="library-image-popover__delete">
                ${label ? 'Supprimer la référence (garder le mot)' : 'Supprimer la référence'}
            </button>
            <button type="button" data-action="close" class="library-image-popover__close">OK</button>
        </div>`

    // Le nœud à sa place actuelle : le texte a pu bouger depuis l'ouverture.
    const locate = () => {
        const pos = getPos()
        const node = typeof pos === 'number' ? editor.view.state.doc.nodeAt(pos) : null

        return node?.type.name === NAME ? { pos, node } : null
    }

    const setText = (field, value) => {
        const found = locate()

        if (found) {
            editor.view.dispatch(editor.view.state.tr.setNodeMarkup(found.pos, undefined, { ...found.node.attrs, [field]: textOf(value) }))
        }
    }

    const outside = (event) => {
        if (!el.contains(event.target) && !anchor.contains(event.target)) {
            close()
        }
    }

    // Sous le chip, sans déborder du cadre de l'éditeur.
    const place = () => {
        if (!anchor.isConnected) {
            return close()
        }

        const frame = host.getBoundingClientRect()
        const chip = anchor.getBoundingClientRect()
        const left = Math.min(chip.left - frame.left, host.clientWidth - el.offsetWidth - 4)

        el.style.left = `${Math.max(4, left)}px`
        el.style.top = `${chip.bottom - frame.top + 6}px`
    }

    const close = ({ refocus = false } = {}) => {
        document.removeEventListener('mousedown', outside, true)
        el.remove()
        anchor.classList.remove('is-editing')
        popover = null

        if (refocus && editor.isEditable) {
            editor.view.focus()
        }
    }

    el.querySelectorAll('input[data-field]').forEach((input) => {
        input.value = current().attrs[input.dataset.field] ?? ''
        input.addEventListener('input', () => setText(input.dataset.field, input.value))
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === 'Escape') {
                event.preventDefault()
                event.stopPropagation()
                close({ refocus: true })
            }
        })
    })

    // Supprimer la référence ; le mot qu'elle avait englobé redevient du texte.
    el.querySelector('[data-action="delete"]').addEventListener('click', () => {
        const found = locate()

        if (found) {
            const { tr, schema } = editor.view.state
            const word = textOf(found.node.attrs.label)
            const end = found.pos + found.node.nodeSize

            editor.view.dispatch(word ? tr.replaceWith(found.pos, end, schema.text(word)) : tr.delete(found.pos, end))
        }

        close({ refocus: true })
    })

    el.querySelector('[data-action="close"]').addEventListener('click', () => close({ refocus: true }))

    host.append(el)
    anchor.classList.add('is-editing')
    place()
    document.addEventListener('mousedown', outside, true)
    popover = { anchor, place, close }
    el.querySelector('input')?.focus()
}

/*
 * Le repère du glisser : où la référence s'écrira si on lâche, ou le mot qu'elle englobera. L'état du plugin est
 * `{ pos, word, armed }` — `word` est le mot sous le pointeur, `armed` dit s'il est englobé (le pointeur y est resté
 * `WORD_DELAY`) — ou null hors d'un glisser. Il ne change que par les méta-données de ses transactions (voir `aim`).
 */
const dropKey = new PluginKey(`${NAME}Drop`)

// Tant qu'un glisser de la bibliothèque survole un éditeur, le repère fin de Filament (Dropcursor) s'efface devant le
// nôtre : voir library-image.css.
const DRAGGING_CLASS = 'library-image-dragging'

const caret = () => {
    const el = document.createElement('span')

    el.className = 'library-image-drop-caret fi-not-prose'

    return el
}

const sameTarget = (a, b) =>
    a === b ||
    (a !== null &&
        b !== null &&
        a.pos === b.pos &&
        a.armed === b.armed &&
        a.word?.from === b.word?.from &&
        a.word?.to === b.word?.to)

// Le glisser en cours dans chaque éditeur : le mot en attente d'être englobé et son minuteur, et la sortie différée
// (voir `dragleave`).
const pending = new WeakMap()
const leaving = new WeakMap()

function aim(view, target) {
    if (!sameTarget(dropKey.getState(view.state), target)) {
        view.dispatch(view.state.tr.setMeta(dropKey, target))
    }

    document.body.classList.toggle(DRAGGING_CLASS, target !== null)
}

function clearAim(view) {
    clearTimeout(pending.get(view)?.timer)
    clearTimeout(leaving.get(view))
    pending.delete(view)
    leaving.delete(view)
    aim(view, null)
}

function followDrag(view, event) {
    clearTimeout(leaving.get(view))
    leaving.delete(view)

    const at = view.posAtCoords({ left: event.clientX, top: event.clientY })

    if (!at) {
        return clearAim(view)
    }

    const word = wordAt(view.state.doc, at.pos)
    const waiting = pending.get(view)

    if (!word) {
        clearTimeout(waiting?.timer)
        pending.delete(view)

        return aim(view, { pos: at.pos, word: null, armed: false })
    }

    // Toujours le même mot : on attend, ou il est déjà englobé.
    if (waiting?.word.from === word.from && waiting.word.to === word.to) {
        return aim(view, { pos: at.pos, word, armed: waiting.armed })
    }

    clearTimeout(waiting?.timer)

    const next = { word, armed: false, timer: null }

    next.timer = setTimeout(() => {
        const current = dropKey.getState(view.state)

        if (pending.get(view) === next && current) {
            next.armed = true
            aim(view, { ...current, word, armed: true })
        }
    }, WORD_DELAY)

    pending.set(view, next)
    aim(view, { pos: at.pos, word, armed: false })
}

const isLibraryDrag = (event) => Array.from(event.dataTransfer?.types ?? []).includes(DRAG_TYPE)

// Un glisser abandonné (Échap, lâché ailleurs) ne laisse pas de repère derrière lui.
const views = new Set()

document.addEventListener('dragend', () => views.forEach(clearAim))

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
            // Le mot du texte que la référence a englobé : le carnet l'écrit, cliquable, au lieu de « (image 3) ».
            label: {
                default: null,
                parseHTML: (element) => textOf(element.getAttribute('data-label')),
                renderHTML: (attributes) => (textOf(attributes.label) ? { 'data-label': textOf(attributes.label) } : {}),
            },
            // Un titre, que le carnet montre au survol de la référence.
            title: {
                default: null,
                parseHTML: (element) => textOf(element.getAttribute('data-title')),
                renderHTML: (attributes) => (textOf(attributes.title) ? { 'data-title': textOf(attributes.title) } : {}),
            },
        }
    },

    parseHTML() {
        return [{ tag: `span[data-type="${NAME}"]` }]
    },

    // Le HTML enregistré : un span vide qui ne porte que les clés, le genre, et les textes facultatifs. Les numéros
    // seraient périmés au premier réordonnancement ; l'éditeur et le carnet les recalculent.
    renderHTML({ HTMLAttributes }) {
        return ['span', mergeAttributes({ 'data-type': NAME }, this.options.HTMLAttributes, HTMLAttributes)]
    },

    renderText({ node }) {
        return textOf(node.attrs.label) ?? `(${LABELS[kindOf(node.attrs.kind)][0].toLowerCase()})`
    },

    addNodeView() {
        return ({ node, editor, getPos }) => {
            const ids = idsOf((node.attrs.media ?? []).join(','))
            const kind = kindOf(node.attrs.kind)
            const dom = document.createElement('span')
            let current = node

            dom.className = `library-image-ref is-${kind}`
            dom.setAttribute('data-type', NAME)
            dom.setAttribute('data-media', ids.join(','))
            dom.setAttribute('data-kind', kind)
            dom.contentEditable = 'false'
            // Un chip : l'icône du genre, le mot englobé, puis pour chaque fichier sa miniature (une image, quand on la
            // connaît, qu'il n'y en a pas trop et qu'aucun mot ne prend la place) et son numéro actuel ; au bout, la bulle
            // d'un titre. Chaque pièce a sa taille bornée dans library-image.css — une image ou un SVG sans taille
            // s'étalerait sur toute la ligne.
            dom.innerHTML =
                `${ICONS[kind]}<span class="library-image-ref__label"></span>` +
                `<span class="library-image-ref__items"></span>${ICONS.title}`

            const labelEl = dom.querySelector('.library-image-ref__label')
            const list = dom.querySelector('.library-image-ref__items')

            const refresh = () => {
                const label = textOf(current.attrs.label)
                const title = textOf(current.attrs.title)
                const positions = ids.map((id) => positionOf(id))
                const showThumbs = kind === 'image' && ids.length <= 2 && !label

                labelEl.textContent = label ?? ''
                dom.classList.toggle('has-label', label !== null)
                dom.classList.toggle('has-title', title !== null)

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

                const plural = LABELS[kind][ids.length > 1 ? 1 : 0]
                const known = positions.filter((position) => position !== null)

                dom.classList.toggle('is-orphan', known.length === 0)
                dom.title = [
                    title ? `« ${title} »` : null,
                    known.length === ids.length
                        ? `${plural} n° ${known.join(', ')} de la période (clés ${ids.join(', ')}) : elles restent liées si on les réordonne`
                        : `${plural} ${ids.join(', ')} : ${known.length === 0 ? 'plus dans' : 'en partie hors de'} la période`,
                    editor.isEditable ? 'Cliquer pour lui donner un titre, ou la supprimer' : null,
                ]
                    .filter(Boolean)
                    .join('\n')

                if (popover?.anchor === dom) {
                    popover.place()
                }
            }

            const enter = () => ids.forEach((id) => announceHover(id, true))
            const leave = () => ids.forEach((id) => announceHover(id, false))

            refresh()
            refreshers.add(refresh)
            dom.addEventListener('mouseenter', enter)
            dom.addEventListener('mouseleave', leave)
            dom.addEventListener('click', (event) => {
                if (!editor.isEditable || typeof getPos !== 'function') {
                    return
                }

                event.preventDefault()

                if (popover?.anchor === dom) {
                    return closePopover()
                }

                openPopover({ editor, getPos, anchor: dom, current: () => current })
            })

            return {
                dom,
                // Les mêmes fichiers du même genre : on garde le chip, et on redessine son mot et son titre.
                update: (updated) => {
                    if (
                        updated.type !== node.type ||
                        kindOf(updated.attrs.kind) !== kind ||
                        (updated.attrs.media ?? []).join(',') !== ids.join(',')
                    ) {
                        return false
                    }

                    current = updated
                    refresh()

                    return true
                },
                destroy: () => {
                    refreshers.delete(refresh)
                    leave()

                    if (popover?.anchor === dom) {
                        closePopover()
                    }
                },
            }
        }
    },

    addProseMirrorPlugins() {
        return [
            new Plugin({
                key: dropKey,
                state: {
                    init: () => null,
                    apply: (tr, value) => {
                        const meta = tr.getMeta(dropKey)

                        if (meta !== undefined) {
                            return meta
                        }

                        // Le texte a changé sous le glisser : le repère attendra le prochain mouvement.
                        return tr.docChanged ? null : value
                    },
                },
                view: (view) => {
                    views.add(view)

                    return {
                        destroy: () => {
                            clearAim(view)
                            views.delete(view)
                        },
                    }
                },
                props: {
                    decorations: (state) => {
                        const target = dropKey.getState(state)

                        if (!target) {
                            return null
                        }

                        const word = target.word && target.word.to <= state.doc.content.size ? target.word : null

                        if (word && target.armed) {
                            return DecorationSet.create(state.doc, [
                                Decoration.inline(word.from, word.to, { class: 'library-image-drop-word' }),
                            ])
                        }

                        const decorations = [Decoration.widget(target.pos, caret, { side: -1, key: 'library-image-drop-caret' })]

                        // En attente d'être englobé : le mot se souligne déjà.
                        if (word) {
                            decorations.push(Decoration.inline(word.from, word.to, { class: 'library-image-drop-word is-pending' }))
                        }

                        return DecorationSet.create(state.doc, decorations)
                    },
                    handleDOMEvents: {
                        dragover: (view, event) => {
                            if (isLibraryDrag(event) && view.editable) {
                                followDrag(view, event)
                            }

                            return false
                        },
                        // Passer d'un élément du texte à un autre (un mot en gras, le repère) sort de l'un avant d'entrer
                        // dans l'autre : on ne retire le repère que si aucun survol ne suit.
                        dragleave: (view) => {
                            clearTimeout(leaving.get(view))
                            leaving.set(
                                view,
                                setTimeout(() => clearAim(view), 80),
                            )

                            return false
                        },
                    },
                    handleDrop: (view, event) => {
                        const raw = event.dataTransfer?.getData(DRAG_TYPE)
                        const target = dropKey.getState(view.state)

                        clearAim(view)

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

                        const { schema, doc } = view.state
                        const content = []

                        // Le mot englobé devient la première référence ; les espaces autour sont déjà celles du texte.
                        const word = target?.armed ? target.word : null
                        const wrapsWord =
                            word !== null &&
                            word.to <= doc.content.size &&
                            doc.textBetween(word.from, word.to, undefined, LEAF) === word.text

                        groups.forEach((group, index) => {
                            if (index > 0) {
                                content.push(schema.text(' '))
                            }

                            content.push(
                                type.create({
                                    media: group.media,
                                    kind: group.kind,
                                    label: wrapsWord && index === 0 ? word.text : null,
                                }),
                            )
                        })

                        if (wrapsWord) {
                            view.dispatch(view.state.tr.replaceWith(word.from, word.to, content))
                        } else {
                            // Les références, séparées d'une espace, avec une espace de chaque côté quand il en manque (voir
                            // planSpacing).
                            const $at = doc.resolve(at.pos)
                            const edge = (node, pick) => (node ? (node.isText ? pick(node.text) : '') : null)
                            const plan = planSpacing(
                                edge($at.nodeBefore, (text) => text.slice(-1)),
                                edge($at.nodeAfter, (text) => text.charAt(0)),
                            )

                            if (plan.before) {
                                content.unshift(schema.text(plan.before))
                            }

                            if (plan.after) {
                                content.push(schema.text(plan.after))
                            }

                            view.dispatch(view.state.tr.replaceWith(plan.replacePrevious ? at.pos - 1 : at.pos, at.pos, content))
                        }

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
