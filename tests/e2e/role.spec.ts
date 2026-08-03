import { expect, test } from '@playwright/test';
import { getBrowserErrors, login, runWp } from './helpers';
import { reseedFixtures } from './reseed-fixtures';

// The suite-wide storageState is the admin session; this test logs in as its
// own user, so start from a clean context.
test.use({ storageState: { cookies: [], origins: [] } });

test.afterEach(async ({ page }) => {
    try {
        expect(await getBrowserErrors(page)).toEqual([]);
    } finally {
        reseedFixtures();
    }
});

/**
 * A role holding only the intended TekstTV capabilities (no manage_options)
 * must be able to open and save the settings page.
 */
test('custom-capability role can open and save settings', async ({ page }) => {
    await login(page, 'teksttv_editor', 'password');

    await page.goto('/wp-admin/admin.php?page=teksttv-settings');
    await expect(page.locator('input[name="teksttv_duration_text"]')).toBeVisible();

    await page.fill('input[name="teksttv_duration_text"]', '37');
    await page.click('#submit');

    await expect(page.locator('input[name="teksttv_duration_text"]')).toHaveValue('37');
});

test('content-only role cannot open prompts without a supported text generator', async ({ page }) => {
    await login(page, 'teksttv_content_editor', 'password');

    const response = await page.context().request.get('/wp-admin/admin.php?page=teksttv-content');
    expect(response.status()).toBe(403);

    const saved = JSON.parse(runWp('option', 'get', 'teksttv_ai_prompts', '--format=json'));
    expect(saved).toMatchObject({
        region_taxonomy: 'category',
        provider: 'protected-provider',
        model: 'protected-provider/protected-model',
        temperature: 0.4,
        top_p: 0.8,
        max_tokens: 1024,
    });
});
