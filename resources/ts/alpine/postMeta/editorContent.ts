/** Tekst-TV editor textarea of TinyMCE-instantie. */
export function getTeksttvEditorHtml(): string {
    const editor = typeof tinymce !== 'undefined' ? tinymce?.get('teksttv_content') : null;
    if (editor && !editor.isHidden()) {
        return editor.getContent();
    }
    const ta = document.querySelector<HTMLTextAreaElement>('#teksttv_content');
    return ta?.value ?? '';
}

export interface CurrentPostEditorState {
    title: string;
    content: string;
}

/** Read unsaved source content from Gutenberg or the Classic Editor. */
export function getCurrentPostEditorState(): CurrentPostEditorState | null {
    const editorStore = wp.data?.select('core/editor');
    if (editorStore) {
        const title = editorStore.getEditedPostAttribute('title');
        const content = editorStore.getEditedPostAttribute('content');
        if (typeof title === 'string' && typeof content === 'string') {
            return { title, content };
        }
    }

    const titleInput = document.querySelector<HTMLInputElement>('#title');
    const contentTextarea = document.querySelector<HTMLTextAreaElement>('#content');
    const editor = typeof tinymce !== 'undefined' ? tinymce?.get('content') : null;
    if (!titleInput || (!editor && !contentTextarea)) return null;

    return {
        title: titleInput.value,
        content: editor && !editor.isHidden() ? editor.getContent() : (contentTextarea?.value ?? ''),
    };
}
