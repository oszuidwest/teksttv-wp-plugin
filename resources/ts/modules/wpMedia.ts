import type { WPMediaFrame, WPMediaOptions } from './types';

/**
 * wp.media expects Underscore on `_` (needs `_.defaults`). Gutenberg uses Lodash via
 * `window.lodash`, but plugins like Yoast SEO also assign Lodash to `_` after page load.
 * PHP saves a verified Underscore snapshot in `window.wpUnderscore`
 * (see Helpers::enqueue_admin_script).
 */
function ensureUnderscore(): void {
    const saved = window.wpUnderscore;
    if (!saved || typeof saved.defaults !== 'function') {
        return;
    }
    const current = window._ as { defaults?: unknown } | undefined;
    if (!current || typeof current.defaults !== 'function') {
        window._ = saved;
    }
}

/** Open wp.media after ensuring Underscore owns `_`. */
export function wpMedia(options: WPMediaOptions): WPMediaFrame {
    ensureUnderscore();
    return wp.media(options);
}

/**
 * Gutenberg and third-party plugins open the media library outside our wpMedia wrapper.
 * Restore `_` on interaction and after late scripts (Yoast SEO) finish loading.
 */
export function guardUnderscoreForMedia(): void {
    document.addEventListener('click', ensureUnderscore, true);
    document.addEventListener('focusin', ensureUnderscore, true);
    window.addEventListener('load', ensureUnderscore);
}
