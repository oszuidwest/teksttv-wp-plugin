import { getBrowserErrors, login } from './helpers';
import { reseedFixtures } from './reseed-fixtures';
import { expect, test } from './test';

// This role test needs a clean session instead of admin storageState.
test.use({ storageState: { cookies: [], origins: [] } });

test.afterEach(async ({ page, runWordPressPHPFile }) => {
    try {
        expect(await getBrowserErrors(page)).toEqual([]);
    } finally {
        await reseedFixtures({ runWordPressPHPFile });
    }
});

test('custom-capability role can access commercials and save settings', async ({ page }) => {
    await login(page, 'teksttv_editor', 'password');

    await page.goto('/wp-admin/admin.php?page=teksttv-commercials');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Reclame');

    await page.goto('/wp-admin/admin.php?page=teksttv-settings');
    await expect(page.locator('input[name="teksttv_duration_text"]')).toBeVisible();

    await page.fill('input[name="teksttv_duration_text"]', '37');
    await page.click('#submit');

    await expect(page.locator('input[name="teksttv_duration_text"]')).toHaveValue('37');
});
