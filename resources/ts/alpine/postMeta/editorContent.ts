/** Tekst-TV editor textarea of TinyMCE-instantie. */
export function getTeksttvEditorHtml(): string {
    const editor = typeof tinymce !== 'undefined' ? tinymce?.get('teksttv_content') : null;
    if (editor && !editor.isHidden()) {
        return editor.getContent();
    }
    const ta = document.querySelector<HTMLTextAreaElement>('#teksttv_content');
    return ta?.value ?? '';
}
