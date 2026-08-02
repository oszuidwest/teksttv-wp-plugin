import { expect, type Page, test } from '@playwright/test';

/** Open the smoke-post editor and wait until the Gutenberg store serves the post. */
async function openSmokePostEditor(page: Page): Promise<void> {
    await page.goto('/wp-admin/edit.php');
    await page.getByRole('link', { name: 'TekstTV Smoke Post' }).first().click();
    await expect(page.locator('#teksttv_meta')).toBeAttached();
    await page.waitForFunction(() => {
        const browser = window as unknown as {
            wp?: { data?: { select(store: string): { getCurrentPostType?(): string } | null } };
        };
        return browser.wp?.data?.select('core/editor')?.getCurrentPostType?.() === 'post';
    });
}

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
        await openSmokePostEditor(page);
        await expect(page.locator('.teksttv-generate-btn')).toHaveCount(0);
    });

    test('AI generation sends the latest unsaved Gutenberg state', async ({ page }) => {
        await openSmokePostEditor(page);

        page.on('dialog', (dialog) => dialog.accept());
        const generateUrl = await page.evaluate(() => {
            const browser = window as unknown as { teksttvPost: { generateUrl: string } };
            return browser.teksttvPost.generateUrl;
        });
        await page.route(`${generateUrl}*`, (route) =>
            route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ content: 'AI tekst' }),
            }),
        );

        const requestPromise = page.waitForRequest((request) => request.url().startsWith(generateUrl), {
            timeout: 5000,
        });
        const invocation = await page.evaluate(() => {
            const browser = window as unknown as {
                wp: { data: { dispatch(store: string): { editPost(values: Record<string, string>): void } } };
                teksttvPost: { aiSupported: boolean; isNewPost: boolean };
            };
            browser.wp.data.dispatch('core/editor').editPost({
                title: 'Onopgeslagen E2E titel',
                content: '<p>Onopgeslagen E2E artikeltekst</p>',
            });
            browser.teksttvPost.aiSupported = true;
            browser.teksttvPost.isNewPost = false;

            const button = document.createElement('button');
            button.dataset.field = 'body';
            const metaBox = document.querySelector<
                HTMLElement & {
                    _x_dataStack?: Array<{ onGenerateClick(event: { currentTarget: HTMLButtonElement }): void }>;
                }
            >('.teksttv-meta-box');
            const component = metaBox?._x_dataStack?.[0];
            if (!component) throw new Error('TekstTV Alpine component is unavailable.');
            component.onGenerateClick({ currentTarget: button });
            return { disabled: button.disabled, html: button.innerHTML };
        });
        expect(invocation.disabled).toBe(true);

        const request = await requestPromise;
        expect(request.postDataJSON()).toMatchObject({
            source_title: 'Onopgeslagen E2E titel',
            source_content: '<p>Onopgeslagen E2E artikeltekst</p>',
        });
    });
});
