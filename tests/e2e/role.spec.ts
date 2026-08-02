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

test('content-only role saves prompts without exposing or overwriting technical fields', async ({ page }) => {
    await login(page, 'teksttv_content_editor', 'password');

    await page.goto('/wp-admin/admin.php?page=teksttv-content');
    const form = page.locator('form.teksttv-settings-form');
    await expect(form.locator('textarea[name="teksttv_ai_prompts[system]"]')).toBeVisible();
    for (const field of ['region_taxonomy', 'provider', 'model', 'temperature', 'top_p', 'max_tokens']) {
        await expect(form.locator(`[name="teksttv_ai_prompts[${field}]"]`)).toHaveCount(0);
    }

    await form.locator('textarea[name="teksttv_ai_prompts[system]"]').fill('Aangepast door contentredactie');
    await form.evaluate((element) => {
        const injected = {
            region_taxonomy: 'post_tag',
            provider: 'attacker',
            model: 'attacker/model',
            temperature: '2',
            top_p: '0.1',
            max_tokens: '8192',
        };
        for (const [key, value] of Object.entries(injected)) {
            const input = document.createElement('input');
            input.name = `teksttv_ai_prompts[${key}]`;
            input.value = value;
            element.append(input);
        }
    });
    await form.locator('#submit').click();

    await expect(form.locator('textarea[name="teksttv_ai_prompts[system]"]')).toHaveValue(
        'Aangepast door contentredactie',
    );
    const saved = JSON.parse(runWp('option', 'get', 'teksttv_ai_prompts', '--format=json'));
    expect(saved).toMatchObject({
        system: 'Aangepast door contentredactie',
        region_taxonomy: 'category',
        provider: 'protected-provider',
        model: 'protected-provider/protected-model',
        temperature: 0.4,
        top_p: 0.8,
        max_tokens: 1024,
    });
});
