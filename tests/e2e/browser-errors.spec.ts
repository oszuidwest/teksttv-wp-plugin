import { expect, test } from '@playwright/test';
import { getBrowserErrors } from './helpers';

const UNLOAD_VIOLATION = 'Permissions policy violation: unload is not allowed in this document.';

for (const [script, reported] of [
    ['/wp-includes/js/tinymce/wp-tinymce.js?ver=1', false],
    ['/wp-content/plugins/teksttv/assets/admin.js?ver=1', true],
] as const) {
    test(`getBrowserErrors ${reported ? 'reports' : 'skips'} the unload violation from ${script}`, async ({ page }) => {
        await page.route('http://errors.test/**', (route) =>
            new URL(route.request().url()).pathname === '/'
                ? route.fulfill({ contentType: 'text/html', body: `<script src="${script}"></script>` })
                : route.fulfill({ contentType: 'text/javascript', body: "addEventListener('unload', () => {});" }),
        );
        await page.goto('http://errors.test/');

        expect(await getBrowserErrors(page)).toEqual(
            reported ? [`console.error: ${UNLOAD_VIOLATION} (http://errors.test${script})`] : [],
        );
    });
}
