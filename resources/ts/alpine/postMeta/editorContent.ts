/** Zichtbare TinyMCE-instantie of de bijbehorende textarea; null als geen van beide bestaat. */
function getEditorHtml(id: string): string | null {
    const editor = typeof tinymce !== 'undefined' ? tinymce?.get(id) : null;
    if (editor && !editor.isHidden()) {
        return editor.getContent();
    }
    return document.querySelector<HTMLTextAreaElement>(`#${id}`)?.value ?? null;
}

/** Tekst-TV editor textarea of TinyMCE-instantie. */
export function getTeksttvEditorHtml(): string {
    return getEditorHtml('teksttv_content') ?? '';
}

/** Read unsaved source content from Gutenberg or the Classic Editor. */
export function getCurrentPostEditorState() {
    const editorStore = wp.data?.select('core/editor');
    if (editorStore) {
        const title = editorStore.getEditedPostAttribute('title');
        const content = editorStore.getEditedPostAttribute('content');
        if (typeof title === 'string' && typeof content === 'string') {
            return { title, content };
        }
    }

    const titleInput = document.querySelector<HTMLInputElement>('#title');
    const content = getEditorHtml('content');
    if (!titleInput || content === null) return null;

    return { title: titleInput.value, content };
}
