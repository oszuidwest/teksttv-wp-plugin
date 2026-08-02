import type { WPTinyMCEEditor } from '../../modules/types';

/** Tekst-TV editor textarea of TinyMCE-instantie. */
export function getTeksttvEditorHtml(): string {
    const editor = typeof tinymce !== 'undefined' ? tinymce?.get('teksttv_content') : null;
    if (editor && !editor.isHidden()) {
        return editor.getContent();
    }
    const ta = document.querySelector<HTMLTextAreaElement>('#teksttv_content');
    return ta?.value ?? '';
}

/** Refresh derived editor state for both TinyMCE input and keyboard events. */
export function bindTeksttvEditorChanges(editor: WPTinyMCEEditor, onChange: () => void): void {
    editor.on('input change keyup SetContent', onChange);
}
