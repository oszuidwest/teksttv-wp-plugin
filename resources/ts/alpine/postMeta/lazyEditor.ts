const EDITOR_ID = 'teksttv_content';

/**
 * Initialize the Tekst TV TinyMCE editor from JS, reusing the config WordPress
 * already generated in `tinyMCEPreInit.mceInit`. Returns true once the editor
 * exists (or already existed).
 *
 * The editor is rendered with `wp_skip_init`, so WordPress does not initialize
 * it on page load. Letting wp_editor() auto-initialize inside a metabox is
 * unsafe: the block editor renders metaboxes hidden and moves them into place
 * after it mounts, and per the wp_editor() docs "the TinyMCE editor cannot be
 * safely moved in the DOM" once initialized — the result is an editor with no
 * caret that cannot be typed in. Initializing only once the container is
 * rendered (not display:none) avoids that entirely.
 */
export function initEditor(): boolean {
    if (typeof tinymce === 'undefined' || !tinymce) return false;
    if (!tinymce.get(EDITOR_ID)) {
        // mceAddEditor runs tinymce.init() with the stored mceInit config.
        tinymce.execCommand('mceAddEditor', false, EDITOR_ID);
    }
    return !!tinymce.get(EDITOR_ID);
}

/**
 * Initialize the editor once its container is rendered. "Rendered" means it has
 * a layout box (not display:none) — deliberately not "scrolled into view": an
 * off-screen editor still lays out correctly, and TinyMCE only breaks when it
 * initializes inside a display:none subtree. This covers both ways the field
 * starts hidden: the block editor revealing metaboxes after mount, and the
 * "Toon op Tekst TV" toggle collapsing the fields.
 */
export function initTeksttvEditorWhenDisplayed(): void {
    const wrap = document.getElementById(`wp-${EDITOR_ID}-wrap`);
    if (!wrap) return;

    // WordPress may expose TinyMCE asynchronously; retry briefly.
    const run = (): void => {
        if (initEditor()) return;
        let attempts = 0;
        const timer = window.setInterval(() => {
            if (initEditor() || ++attempts >= 50) window.clearInterval(timer);
        }, 100);
    };

    const isDisplayed = (): boolean => wrap.getClientRects().length > 0;

    if (isDisplayed() || typeof ResizeObserver === 'undefined') {
        run();
        return;
    }

    // A display:none element reports no client rects and a zero content box;
    // ResizeObserver fires when it gains a layout box, regardless of viewport.
    const observer = new ResizeObserver(() => {
        if (isDisplayed()) {
            observer.disconnect();
            run();
        }
    });
    observer.observe(wrap);
}
