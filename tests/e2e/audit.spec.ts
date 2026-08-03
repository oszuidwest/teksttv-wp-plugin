import { expect, test } from '@playwright/test';

test.describe('unconfigured AI administration', () => {
    test('keeps Content & AI and AI Audit unavailable without a supported text generator', async ({ request }) => {
        const [contentResponse, auditResponse] = await Promise.all([
            request.get('/wp-admin/admin.php?page=teksttv-content'),
            request.get('/wp-admin/admin.php?page=teksttv-audit'),
        ]);

        expect(contentResponse.status()).toBe(403);
        expect(auditResponse.status()).toBe(403);
    });
});
