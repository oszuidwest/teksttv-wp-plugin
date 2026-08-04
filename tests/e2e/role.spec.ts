import { getBrowserErrors, login } from './helpers';
import { reseedFixtures } from './reseed-fixtures';
import { expect, test } from './test';

// The suite-wide storageState is the admin session; this test logs in as its
// own user, so start from a clean context.
test.use({ storageState: { cookies: [], origins: [] } });

test.afterEach(async ({ page, runWordPressPHP }) => {
    try {
        expect(await getBrowserErrors(page)).toEqual([]);
    } finally {
        await reseedFixtures(runWordPressPHP);
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
