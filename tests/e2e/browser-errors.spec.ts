import { expect, test } from '@playwright/test';
import { getBrowserErrors } from './helpers';

const UNLOAD_VIOLATION = 'Permissions policy violation: unload is not allowed in this document.';
const UNLOAD_LISTENER = "addEventListener('unload', () => {});";
const TINYMCE = '/wp-includes/js/tinymce/wp-tinymce.js?ver=1';
const TINYMCE_MIN = '/wp-includes/js/tinymce/tinymce.min.js?ver=1';
const PLUGIN = '/wp-content/plugins/teksttv/assets/admin.js?ver=1';

for (const [title, script, body, errors] of [
    ['skips the unload violation from core wp-tinymce.js', TINYMCE, UNLOAD_LISTENER, []],
    ['skips the unload violation from core tinymce.min.js', TINYMCE_MIN, UNLOAD_LISTENER, []],
    ['reports the unload violation from a plugin script', PLUGIN, UNLOAD_LISTENER, [UNLOAD_VIOLATION]],
    ['reports other errors from core TinyMCE', TINYMCE, "console.error('other error');", ['other error']],
] as const) {
    test(`getBrowserErrors ${title}`, async ({ page }) => {
        await page.route('http://errors.test/**', (route) =>
            new URL(route.request().url()).pathname === '/'
                ? route.fulfill({ contentType: 'text/html', body: `<script src="${script}"></script>` })
                : route.fulfill({ contentType: 'text/javascript', body }),
        );
        await page.goto('http://errors.test/');

        expect(await getBrowserErrors(page)).toEqual(
            errors.map((error) => `console.error: ${error} (http://errors.test${script})`),
        );
    });
}
