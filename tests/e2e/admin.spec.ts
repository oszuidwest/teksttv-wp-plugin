import { expect, test } from '@playwright/test';
import { getBrowserErrors } from './helpers';
import { reseedFixtures } from './reseed-fixtures';

test.afterEach(async ({ page }) => {
    try {
        expect(await getBrowserErrors(page)).toEqual([]);
    } finally {
        reseedFixtures();
    }
});

test.describe('administrator admin screens', () => {
    test('settings page renders core controls', async ({ page }) => {
        await page.goto('/wp-admin/admin.php?page=teksttv-settings');
        await expect(page.locator('input[name="teksttv_duration_text"]')).toBeVisible();
        await expect(page.locator('#submit')).toBeVisible();
    });

    test('administrator can save settings', async ({ page }) => {
        await page.goto('/wp-admin/admin.php?page=teksttv-settings');
        await page.fill('input[name="teksttv_duration_text"]', '42');
        await page.click('#submit');
        // The Settings API reloads the page; the saved value must persist.
        await expect(page.locator('input[name="teksttv_duration_text"]')).toHaveValue('42');
    });

    test('loop page renders the blocks workbench', async ({ page }) => {
        await page.goto('/wp-admin/admin.php?page=teksttv-loop-tv1');
        await expect(page.locator('#teksttv-blocks')).toBeVisible();
    });

    test('post editor hides AI controls when no provider connector is configured', async ({ page }) => {
        await page.goto('/wp-admin/edit.php');
        await page.getByRole('link', { name: 'TekstTV Smoke Post' }).first().click();
        await expect(page.locator('#teksttv_meta')).toBeAttached();
        await expect(page.locator('.teksttv-generate-btn')).toHaveCount(0);
    });
});
