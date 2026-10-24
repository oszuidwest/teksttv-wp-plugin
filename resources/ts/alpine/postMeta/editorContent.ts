/** Read visible TinyMCE content, falling back to its textarea. */
function getEditorHtml(id: string): string | null {
    const editor = typeof tinymce !== 'undefined' ? tinymce?.get(id) : null;
    if (editor && !editor.isHidden()) {
        return editor.getContent();
    }
    return document.querySelector<HTMLTextAreaElement>(`#${id}`)?.value ?? null;
}

export function getTeksttvEditorHtml(): string {
    return getEditorHtml('teksttv_content') ?? '';
}

function getStoreTitle(): string | null {
    const title = wp.data?.select('core/editor')?.getEditedPostAttribute('title');
    return typeof title === 'string' ? title : null;
}

/** Read the unsaved post title from Gutenberg, falling back to the Classic Editor field. */
export function getCurrentPostTitle(): string {
    return getStoreTitle() ?? document.querySelector<HTMLInputElement>('#title')?.value ?? '';
}

/** Call `onChange` when the Gutenberg title changes. Classic keeps using the `#title` input listener. */
export function watchPostTitle(onChange: () => void): void {
    if (!wp.data?.subscribe) return;
    let previous = getStoreTitle();
    // The store notifies on every change; only a different title is forwarded.
    wp.data.subscribe(() => {
        const current = getStoreTitle();
        if (current === null || current === previous) return;
        previous = current;
        onChange();
    });
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
