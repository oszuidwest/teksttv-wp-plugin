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

    test('post editor hides unconfigured AI controls and fills the available tablet width', async ({ page }) => {
        await page.setViewportSize({ width: 1024, height: 900 });
        await openFixturePostEditor(page);
        await expect(page.locator('.teksttv-generate-btn')).toHaveCount(0);

        const widths = await page.locator('.teksttv-editor-layout').evaluate((layout) => {
            const main = layout.querySelector('.teksttv-editor-main');
            if (!main) throw new Error('Editor main column is missing.');
            return {
                layout: layout.getBoundingClientRect().width,
                main: main.getBoundingClientRect().width,
            };
        });

        expect(Math.abs(widths.layout - widths.main)).toBeLessThan(1);
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

    test('AI generation sends the latest unsaved Gutenberg state', async ({ page }) => {
        await openFixturePostEditor(page);
        await page.waitForFunction(() => {
            const browser = window as unknown as {
                wp?: { data?: { select(store: string): { getCurrentPostType?(): string } | null } };
            };
            return browser.wp?.data?.select('core/editor')?.getCurrentPostType?.() === 'post';
        });

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
        const disabled = await page.evaluate(() => {
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
            return button.disabled;
        });
        expect(disabled).toBe(true);

        const request = await requestPromise;
        expect(request.postDataJSON()).toMatchObject({
            source_title: 'Onopgeslagen E2E titel',
            source_content: '<p>Onopgeslagen E2E artikeltekst</p>',
        });
    });
});
