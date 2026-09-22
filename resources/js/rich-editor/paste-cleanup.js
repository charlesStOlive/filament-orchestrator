/*
 * Nettoyage du collage dans le RichEditor : ce qui vient de Word, d'une page web ou de Google Docs
 * arrive avec ses polices, ses couleurs, ses classes — tout ce que l'éditeur ne sait pas produire
 * lui-même. On ne garde que la structure (titres, gras, italique, listes, liens, tableaux…), jamais
 * les styles ni les classes, et jamais les images : elles ne se collent pas ici, seule la
 * bibliothèque en pose (voir library-image.js). Un <img> collé est donc retiré, pas importé — ce qui
 * suppose que l'import d'images du RichEditor est lui-même désactivé (`fileAttachments(false)`),
 * sans quoi Filament l'uploaderait avant que ce nettoyage n'ait sa chance de le voir.
 *
 * Chargée à la demande par Filament (voir PasteCleanupPlugin), comme library-image.js : pas de
 * build, on réutilise l'instance TipTap de l'éditeur.
 */

const { Extension } = window.FilamentRichEditor.tiptap.core
const { Plugin, PluginKey } = window.FilamentRichEditor.tiptap.pmState

// Ces balises n'apportent jamais de contenu à garder : Word et les pages web les sèment partout
// (styles de page, commentaires de compatibilité Office, polices de repli, images).
const REMOVED_TAGS = new Set(['img', 'style', 'script', 'meta', 'link', 'font', 'o:p', 'v:shapetype', 'v:shape'])

// Seuls ces attributs portent un sens que l'éditeur comprend ; tout le reste (style, class, lang,
// les attributs mso-* et autres) n'est que la mise en forme de la source, laissée derrière.
const KEPT_ATTRIBUTES = {
    a: new Set(['href', 'target', 'rel']),
    td: new Set(['colspan', 'rowspan']),
    th: new Set(['colspan', 'rowspan']),
}

function clean(node) {
    if (node.nodeType === Node.COMMENT_NODE) {
        node.remove()
        return
    }

    if (node.nodeType !== Node.ELEMENT_NODE) {
        return
    }

    const tag = node.tagName.toLowerCase()

    if (REMOVED_TAGS.has(tag)) {
        node.remove()
        return
    }

    const kept = KEPT_ATTRIBUTES[tag]

    ;[...node.attributes].forEach((attribute) => {
        if (!kept?.has(attribute.name)) {
            node.removeAttribute(attribute.name)
        }
    })

    ;[...node.childNodes].forEach(clean)
}

export default Extension.create({
    name: 'pasteCleanup',

    addProseMirrorPlugins() {
        return [
            new Plugin({
                key: new PluginKey('pasteCleanup'),
                props: {
                    transformPastedHTML(html) {
                        const wrapper = document.createElement('div')
                        wrapper.innerHTML = html
                        ;[...wrapper.childNodes].forEach(clean)

                        return wrapper.innerHTML
                    },
                },
            }),
        ]
    },
})
