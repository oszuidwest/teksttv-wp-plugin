import { expect, test } from '@playwright/test';
import { login } from './helpers';

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
