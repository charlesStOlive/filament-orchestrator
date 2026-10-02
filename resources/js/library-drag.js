/*
 * Le fantôme d'un glisser de la bibliothèque (une carte, ou plusieurs cochées) ou du panneau d'images d'une période.
 *
 * Le navigateur prendrait sinon la carte entière pour image du glisser : elle cache le pointeur et l'endroit du texte où
 * l'on vise. On glisse à la place une petite étiquette — « Image 3 » quand le fichier est déjà dans la période (son
 * numéro dans le panneau), son nom sinon, tronqué ; « 3 fichiers » pour plusieurs —, en bas à droite du pointeur (voir
 * `.library-drag-ghost` dans library-image.css).
 *
 * Chargé sur toutes les pages du panneau : les cartes (library/thumbnail) et le panneau (tag-images-panel) l'appellent
 * dans leur `dragstart`.
 */

window.libraryDragGhost = (dataTransfer, items) => {
    if (!dataTransfer?.setDragImage || !items?.length) {
        return
    }

    const words = { image: 'Image', video: 'Vidéo' }

    const labelOf = ({ media, kind }) => {
        const tile = document.querySelector(`[data-library-image="${media}"]`)
        const position = Number(tile?.dataset.libraryPosition)

        if (Number.isInteger(position) && position > 0) {
            return `${words[kind] ?? words.image} ${position}`
        }

        return document.querySelector(`[data-library-card="${media}"]`)?.dataset.libraryName || `${words[kind] ?? words.image}`
    }

    const ghost = document.createElement('div')
    const label = document.createElement('span')

    ghost.className = 'library-drag-ghost'
    label.textContent = items.length > 1 ? `${items.length} fichiers` : labelOf(items[0])
    ghost.append(label)
    document.body.append(ghost)
    dataTransfer.setDragImage(ghost, 0, 0)

    // Le navigateur a pris son image : l'élément peut partir.
    setTimeout(() => ghost.remove())
}

/*
 * Réordonner les vignettes d'un panneau d'images ne doit pas faire bouger la page.
 *
 * Le dépôt enregistre l'ordre, puis la page entière se redessine (ses cartes de période lisent leur couverture dans la
 * bibliothèque) : deux allers-retours, après lesquels la page remontait parfois en haut. Le panneau note la position de
 * la page au début du glisser (`libraryScrollAtDrag`) et, au dépôt, la tient à cette place le temps que tout se
 * redessine. Dès qu'on fait défiler soi-même (molette, doigt, clavier, clic), on reprend la main.
 */
window.libraryHoldScroll = (top, ms = 2500) => {
    if (!Number.isFinite(top)) {
        return
    }

    const until = performance.now() + ms
    const inputs = ['wheel', 'touchmove', 'keydown', 'pointerdown']
    let released = false

    const release = () => {
        released = true
        inputs.forEach((type) => window.removeEventListener(type, release, true))
    }

    inputs.forEach((type) => window.addEventListener(type, release, { capture: true, passive: true }))

    const hold = () => {
        if (released) {
            return
        }

        if (performance.now() > until) {
            return release()
        }

        if (Math.abs(window.scrollY - top) > 1) {
            window.scrollTo({ top, behavior: 'instant' })
        }

        requestAnimationFrame(hold)
    }

    hold()
}

/*
 * Le panneau d'images d'une période se réordonne au glisser (x-sortable de Filament) ; la même vignette se glisse aussi
 * vers le texte, pour y écrire sa référence. Hors du panneau, SortableJS continuerait de la ranger — en bout de rangée
 * dès qu'on passe à droite ou sous la dernière — et le dépôt dans le texte enregistrerait ce nouvel ordre.
 *
 * Le panneau ne se range donc que tant que le pointeur est dans le panneau (`onMove`), et un dépôt ailleurs remet
 * l'ordre d'avant le glisser, sans rien enregistrer : la vue lit `libraryDragInside` au dépôt.
 */
/*
 * Remet les vignettes dans cet ordre, à leur place : avant ce qui les suit dans le panneau (la case « + »). Pas
 * `sortable.sort()`, qui les recolle à la fin, après elle.
 */
const restoreOrder = (list, ids) => {
    const items = [...list.querySelectorAll(':scope > [x-sortable-item]')]
    const byId = new Map(items.map((item) => [item.getAttribute('x-sortable-item'), item]))
    const anchor = [...list.children].find((child) => !child.hasAttribute('x-sortable-item')
        && items[0]?.compareDocumentPosition(child) & Node.DOCUMENT_POSITION_FOLLOWING) ?? null

    ids.forEach((id) => byId.has(id) && list.insertBefore(byId.get(id), anchor))
}

window.libraryPanelSortable = (list) => {
    const sortable = list?.sortable

    if (!sortable || list.libraryPanelSortable) {
        return
    }

    list.libraryPanelSortable = true

    let order = null
    const track = (event) => {
        list.libraryDragInside = list.contains(event.target)
    }

    sortable.option('onMove', (event, originalEvent) => !originalEvent || list.contains(originalEvent.target))

    list.addEventListener('start', () => {
        order = sortable.toArray()
        list.libraryDragInside = true
        document.addEventListener('dragover', track, true)
    })

    // Écouté après le `x-on:end` de la vue (déclaré plus tôt), qui a déjà lu `libraryDragInside`.
    list.addEventListener('end', () => {
        document.removeEventListener('dragover', track, true)

        if (!list.libraryDragInside && order) {
            const before = order

            // Après le onEnd de Filament, qui replace la vignette à l'index où SortableJS l'a laissée.
            setTimeout(() => restoreOrder(list, before))
        }

        order = null
    })
}
