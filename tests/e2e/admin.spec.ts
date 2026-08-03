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

    test('workbench screens share section, heading and action sizing', async ({ page }) => {
        await page.goto('/wp-admin/admin.php?page=teksttv-loop-tv1');
        await expect(page.getByRole('heading', { level: 1 })).toHaveText('Kanaal: TV 1');
        const loopSections = page.locator('.teksttv-workbench-section');
        await expect(loopSections).toHaveCount(2);
        await expect(loopSections.locator(':scope > h2')).toHaveText(['Loop', 'Tickerberichten']);

        const loopActionWidths = await page
            .locator('#teksttv-add-block-toggle, #teksttv-add-ticker-toggle')
            .evaluateAll((buttons) => buttons.map((button) => button.getBoundingClientRect().width));
        expect(Math.max(...loopActionWidths) - Math.min(...loopActionWidths)).toBeLessThan(1);

        await page.goto('/wp-admin/admin.php?page=teksttv-campaigns');
        const campaignSections = page.locator('.teksttv-workbench-section');
        await expect(campaignSections).toHaveCount(2);
        await expect(campaignSections.locator(':scope > h2')).toHaveText(['Groepen', 'Campagnes']);
        const campaignActionWidths = await page
            .locator('#teksttv-add-group, #teksttv-add-campaign')
            .evaluateAll((buttons) => buttons.map((button) => button.getBoundingClientRect().width));
        expect(Math.max(...campaignActionWidths) - Math.min(...campaignActionWidths)).toBeLessThan(1);
    });

    test('campaign layout uses one width contract and responsive field grid', async ({ page }) => {
        await page.goto('/wp-admin/admin.php?page=teksttv-campaigns');

        const groupPanel = page.locator('.teksttv-campaign-groups');
        const campaignPanel = page.locator('.teksttv-campaign-workbench');
        const campaignList = page.locator('#teksttv-campaigns');
        await expect(groupPanel).toBeVisible();
        await expect(campaignPanel).toBeVisible();
        await expect(campaignList).toBeVisible();

        const desktopWidths = await Promise.all([
            groupPanel.evaluate((element) => element.getBoundingClientRect().width),
            campaignPanel.evaluate((element) => element.getBoundingClientRect().width),
        ]);
        expect(desktopWidths[0]).toBeLessThanOrEqual(800);
        expect(Math.abs(desktopWidths[0] - desktopWidths[1])).toBeLessThan(1);

        const firstCampaign = campaignList.locator(':scope > .teksttv-block').first();
        await firstCampaign.locator('.teksttv-block-toggle-control').click();
        await expect(firstCampaign.locator('.teksttv-block-body')).toBeVisible();
        const fields = firstCampaign.locator('.teksttv-field-grid').first().locator(':scope > .teksttv-field');
        await expect(fields).toHaveCount(3);

        const desktopFields = await fields.evaluateAll((elements) =>
            elements.map((element) => element.getBoundingClientRect().width),
        );
        expect(desktopFields[0]).toBeGreaterThan(desktopFields[1]);
        expect(desktopFields[1]).toBeGreaterThan(desktopFields[2]);

        const firstRowLabels = fields.locator(':scope > label');
        const labelTops = await firstRowLabels.evaluateAll((elements) =>
            elements.map((element) => element.getBoundingClientRect().top),
        );
        expect(Math.max(...labelTops) - Math.min(...labelTops)).toBeLessThan(1);

        const nameInput = fields.first().locator('input');
        const startDateLabel = firstCampaign.locator('input[name$="[date_start]"]').locator('..').locator('label');
        const [nameInputBottom, startDateLabelTop] = await Promise.all([
            nameInput.evaluate((element) => element.getBoundingClientRect().bottom),
            startDateLabel.evaluate((element) => element.getBoundingClientRect().top),
        ]);
        expect(startDateLabelTop - nameInputBottom).toBeGreaterThanOrEqual(11);

        const durationRow = fields.last().locator('.teksttv-input-with-unit');
        const [durationInputBox, durationUnitBox] = await Promise.all([
            durationRow.locator('input').evaluate((element) => element.getBoundingClientRect().toJSON()),
            durationRow.locator('.teksttv-unit').evaluate((element) => element.getBoundingClientRect().toJSON()),
        ]);
        expect(durationUnitBox.top).toBeGreaterThan(durationInputBox.top);
        expect(durationUnitBox.bottom).toBeLessThan(durationInputBox.bottom);

        await page.setViewportSize({ width: 760, height: 900 });
        const mobileFields = await fields.evaluateAll((elements) =>
            elements.map((element) => {
                const box = element.getBoundingClientRect();
                return { left: box.left, width: box.width };
            }),
        );
        expect(mobileFields.every(({ left }) => Math.abs(left - mobileFields[0].left) < 1)).toBe(true);
        expect(mobileFields.every(({ width }) => Math.abs(width - mobileFields[0].width) < 1)).toBe(true);
    });

    test('post editor hides unconfigured AI controls and fills the available tablet width', async ({ page }) => {
        await page.setViewportSize({ width: 1024, height: 900 });
        await openFixturePostEditor(page);
        await expect(page.locator('.teksttv-generate-btn')).toHaveCount(0);

        const layout = page.locator('.teksttv-editor-layout');
        const [layoutWidth, mainWidth] = await Promise.all([
            layout.evaluate((element) => element.getBoundingClientRect().width),
            layout.locator('.teksttv-editor-main').evaluate((element) => element.getBoundingClientRect().width),
        ]);

        expect(Math.abs(layoutWidth - mainWidth)).toBeLessThan(1);
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
