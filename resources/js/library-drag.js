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
