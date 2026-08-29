const EDITOR_ID = 'teksttv_content';

/**
 * Initialize the editor from the config WordPress already generated in
 * `tinyMCEPreInit.mceInit` (the view sets `wp_skip_init`, so WordPress does not
 * do this itself). Returns true once the editor exists, or false when TinyMCE
 * or the config is not available yet so the caller can retry.
 */
export function initEditor(): boolean {
    if (typeof tinymce === 'undefined' || !tinymce) return false;
    if (tinymce.get(EDITOR_ID)) return true;

    // Init from WordPress' own stored config, exactly like core's editor init
    // loop. (Do not use execCommand('mceAddEditor'): that builds the editor from
    // EditorManager's last-used settings, so with wp_skip_init it would miss our
    // toolbar and the external "Nieuwe slide" plugin.)
    const preInit = typeof tinyMCEPreInit !== 'undefined' ? tinyMCEPreInit : undefined;
    const config = preInit?.mceInit?.[EDITOR_ID];
    if (!config) return false;

    tinymce.init(config);
    return !!tinymce.get(EDITOR_ID);
}

/**
 * Initialize the editor once its container is rendered — has a layout box, not
 * display:none. Deliberately not "scrolled into view": an off-screen editor
 * lays out correctly, and TinyMCE only breaks inside a display:none subtree.
 * Covers both ways the field starts hidden: the block editor revealing
 * metaboxes after mount, and the "Toon op Tekst TV" toggle.
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
