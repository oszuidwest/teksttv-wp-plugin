import { expect, test } from '@playwright/test';
import { getBrowserErrors, openFixturePostEditor } from './helpers';
import { reseedFixtures } from './reseed-fixtures';

test.afterEach(async ({ page }) => {
    expect(await getBrowserErrors(page)).toEqual([]);
});

test.describe('administrator admin screens', () => {
    test('settings page renders core controls', async ({ page }) => {
        await page.goto('/wp-admin/admin.php?page=teksttv-settings');
        await expect(page.locator('input[name="teksttv_duration_text"]')).toBeVisible();
        await expect(page.locator('#submit')).toBeVisible();
    });

    test.describe('settings mutation', () => {
        test.afterEach(() => {
            reseedFixtures();
        });

        test('administrator can save settings', async ({ page }) => {
            await page.goto('/wp-admin/admin.php?page=teksttv-settings');
            await page.fill('input[name="teksttv_duration_text"]', '42');
            await page.click('#submit');
            // The Settings API reloads the page; the saved value must persist.
            await expect(page.locator('input[name="teksttv_duration_text"]')).toHaveValue('42');
        });
    });

    test('loop page renders the blocks workbench', async ({ page }) => {
        await page.goto('/wp-admin/admin.php?page=teksttv-loop-tv1');
        await expect(page.locator('#teksttv-blocks')).toBeVisible();
    });

    test('post editor hides AI controls when no provider connector is configured', async ({ page }) => {
        await openFixturePostEditor(page);
        await expect(page.locator('.teksttv-generate-btn')).toHaveCount(0);
    });

    test('post editor updates the word count from TinyMCE keyup', async ({ page }) => {
        await openFixturePostEditor(page);
        const editor = page.frameLocator('#teksttv_content_ifr').locator('body');
        await editor.evaluate((body) => {
            // Avoid input/change/SetContent so this specifically covers the keyup fallback.
            body.innerHTML = '<p>Twee woorden</p>';
            const tinyMceEditor = window.parent.tinymce?.get('teksttv_content');
            if (!tinyMceEditor) throw new Error('TinyMCE editor teksttv_content not found.');
            tinyMceEditor.fire('keyup');
        });
        await expect(page.locator('#teksttv-wordcount')).toHaveText(/^2(?: \/ \d+)? woorden$/);
    });
});
