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
