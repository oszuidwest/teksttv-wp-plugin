import { getBrowserErrors, openFixturePostEditor } from './helpers';
import { reseedFixtures } from './reseed-fixtures';
import { expect, test } from './test';

test.afterEach(async ({ page }) => {
    expect(await getBrowserErrors(page)).toEqual([]);
});

test.describe('administrator admin screens', () => {
    test('settings page renders core controls', async ({ page }) => {
        await page.goto('/wp-admin/admin.php?page=teksttv-settings');
        await expect(page.locator('input[name="teksttv_duration_text"]')).toBeVisible();
        await expect(page.locator('#submit')).toBeVisible();
    });

    test.describe('option mutation', () => {
        test.afterEach(reseedFixtures);

        test('administrator can save settings', async ({ page }) => {
            await page.goto('/wp-admin/admin.php?page=teksttv-settings');
            await page.fill('input[name="teksttv_duration_text"]', '42');
            await page.fill('input[name="teksttv_channels[0][slug]"]', 'tv_one');
            const copyEndpoint = page.locator('#teksttv-channels .teksttv-copy-endpoint').first();
            await expect(copyEndpoint).toBeEnabled();
            await expect(copyEndpoint).toHaveAttribute('data-endpoint', /[?&]channel=tv_one$/);
            const saveResponse = page.waitForResponse(
                (response) =>
                    response.request().method() === 'POST' && new URL(response.url()).pathname.endsWith('/options.php'),
            );
            await page.click('#submit');
            // The Settings API reloads the page; the saved value must persist.
            expect((await saveResponse).status()).toBeLessThan(400);
            await expect(page.locator('input[name="teksttv_duration_text"]')).toHaveValue('42');
            await expect(page.locator('input[name="teksttv_channels[0][slug]"]')).toHaveValue('tv_one');
        });

        test('post editor uses a plain textarea when rich text options are disabled', async ({
            page,
            runWordPressPHP,
        }) => {
            await runWordPressPHP("update_option('teksttv_features', ['custom_title', 'page_separator']);");

            await openFixturePostEditor(page);

            const editor = page.locator('#teksttv_content');
            await expect(editor).toBeVisible();
            await expect(page.locator('#teksttv_content_ifr')).toHaveCount(0);
            await expect(page.locator('#wp-teksttv_content-editor-container')).toHaveCount(0);
            await expect(page.locator('#teksttv-title')).toHaveAttribute(
                'placeholder',
                'Laat leeg om de titel van het artikel te gebruiken.',
            );

            await editor.fill('Eerste slide');
            await page.locator('.teksttv-plain-separator').click();
            await expect(editor).toHaveValue('Eerste slide\n---\n');
            await editor.pressSequentially('Tweede slide');
            await expect(editor).toHaveValue('Eerste slide\n---\nTweede slide');

            await editor.fill('Eerste slide\nTweede slide');
            await editor.evaluate((element) => {
                const textarea = element as HTMLTextAreaElement;
                const separatorPosition = 'Eerste slide'.length;
                textarea.setSelectionRange(separatorPosition, separatorPosition);
            });
            await page.locator('.teksttv-plain-separator').click();
            await editor.pressSequentially('Nieuwe ');
            await expect(editor).toHaveValue('Eerste slide\n---\nNieuwe Tweede slide');
        });

        test('post editor keeps the counter and preview on one slide when separators are disabled', async ({
            page,
            runWordPressPHP,
        }) => {
            await runWordPressPHP("update_option('teksttv_features', ['custom_title']);");

            await openFixturePostEditor(page);
            await page.locator('#teksttv_content').fill('Eerste slide\n---\nTweede slide');

            await expect(page.locator('#teksttv-wordcount')).toHaveText(/^5(?: \/ \d+)? woorden$/);
            await expect(page.locator('#teksttv-preview-counter')).toHaveText('1 / 1');
        });

        test('post editor contains the empty preview at narrow widths', async ({ page, runWordPressPHP }) => {
            await runWordPressPHP("delete_option('teksttv_preview_url');");
            await page.setViewportSize({ width: 390, height: 844 });
            await openFixturePostEditor(page);

            const emptyPreview = page.locator('.teksttv-no-preview');
            await expect(emptyPreview).toBeVisible();
            const overflow = await emptyPreview.evaluate((element) => element.scrollHeight - element.clientHeight);

            expect(overflow).toBeLessThanOrEqual(1);
        });
    });

    test('loop page renders the blocks workbench', async ({ page }) => {
        await page.goto('/wp-admin/admin.php?page=teksttv-loop-tv1');
        await expect(page.locator('#teksttv-blocks')).toBeVisible();
    });

    test('legacy campaign URL redirects to the commercials page', async ({ page }) => {
        await page.goto('/wp-admin/admin.php?page=teksttv-campaigns');
        await expect(page).toHaveURL(/admin\.php\?page=teksttv-commercials$/);
        await expect(page.getByRole('heading', { level: 1 })).toHaveText('Reclame');
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
        expect(loopActionWidths.length).toBeGreaterThan(0);
        expect(Math.max(...loopActionWidths)).toBeLessThan(190);

        await page.goto('/wp-admin/admin.php?page=teksttv-commercials');
        await expect(page.getByRole('heading', { level: 1 })).toHaveText('Reclame');
        const campaignSections = page.locator('.teksttv-workbench-section');
        await expect(campaignSections).toHaveCount(2);
        await expect(campaignSections.locator(':scope > h2')).toHaveText(['Reclameblokken', 'Campagnes']);
        const campaignActionWidths = await page
            .locator('#teksttv-add-commercial-block, #teksttv-add-campaign')
            .evaluateAll((buttons) => buttons.map((button) => button.getBoundingClientRect().width));
        expect(campaignActionWidths).toHaveLength(2);
        expect(Math.max(...campaignActionWidths)).toBeLessThan(190);
    });

    test('campaign layout uses one width contract and responsive field grid', async ({ page }) => {
        await page.goto('/wp-admin/admin.php?page=teksttv-commercials');

        const commercialBlocksPanel = page.locator('.teksttv-commercial-blocks');
        const campaignPanel = page.locator('.teksttv-campaign-workbench');
        const campaignList = page.locator('#teksttv-campaigns');
        await expect(commercialBlocksPanel).toBeVisible();
        await expect(campaignPanel).toBeVisible();
        await expect(campaignList).toBeVisible();

        const desktopWidths = await Promise.all([
            commercialBlocksPanel.evaluate((element) => element.getBoundingClientRect().width),
            campaignPanel.evaluate((element) => element.getBoundingClientRect().width),
        ]);
        expect(desktopWidths[0]).toBeLessThanOrEqual(800);
        expect(Math.abs(desktopWidths[0] - desktopWidths[1])).toBeLessThan(1);

        const firstCampaign = campaignList.locator(':scope > .teksttv-block').first();
        await firstCampaign.locator('.teksttv-block-toggle-control').click();
        await expect(firstCampaign.locator('.teksttv-block-body')).toBeVisible();
        const fields = firstCampaign.locator('.teksttv-block-section--content .teksttv-field');
        await expect(fields).toHaveCount(2);

        const desktopFields = await fields.evaluateAll((elements) =>
            elements.map((element) => element.getBoundingClientRect().width),
        );
        expect(Math.abs(desktopFields[0] - desktopFields[1])).toBeLessThan(1);

        const firstRowLabels = fields.locator(':scope > label');
        await expect(firstRowLabels).toHaveCount(2);
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

        const durationRow = firstCampaign.locator('.teksttv-block-section--duration .teksttv-input-with-unit');
        const [durationInputBox, durationUnitBox] = await Promise.all([
            durationRow.locator('input').evaluate((element) => element.getBoundingClientRect().toJSON()),
            durationRow.locator('.teksttv-unit').evaluate((element) => element.getBoundingClientRect().toJSON()),
        ]);
        const durationCenterOffset = Math.abs(
            (durationUnitBox.top + durationUnitBox.bottom) / 2 - (durationInputBox.top + durationInputBox.bottom) / 2,
        );
        expect(durationCenterOffset).toBeLessThanOrEqual(1);
        await expect(durationRow.locator('input')).toHaveAccessibleName('Per slide (seconden)');

        await page.setViewportSize({ width: 760, height: 900 });
        const mobileFields = await fields.evaluateAll((elements) =>
            elements.map((element) => {
                const box = element.getBoundingClientRect();
                return { left: box.left, right: box.right, width: box.width };
            }),
        );
        expect(mobileFields.every(({ width }) => Math.abs(width - mobileFields[0].width) < 1)).toBe(true);
        const mobilePanelBox = await campaignPanel.evaluate((element) => element.getBoundingClientRect().toJSON());
        expect(
            mobileFields.every(({ left, right }) => left >= mobilePanelBox.left && right <= mobilePanelBox.right),
        ).toBe(true);
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

    test('post editor uses an equal split studio on desktop', async ({ page }) => {
        await page.setViewportSize({ width: 1600, height: 1000 });
        await openFixturePostEditor(page);

        const layout = page.locator('.teksttv-editor-layout');
        const main = layout.locator('.teksttv-editor-main');
        const preview = layout.locator('.teksttv-editor-preview');
        const [mainBox, previewBox] = await Promise.all([
            main.evaluate((element) => element.getBoundingClientRect().toJSON()),
            preview.evaluate((element) => element.getBoundingClientRect().toJSON()),
        ]);

        expect(Math.abs(mainBox.width - previewBox.width)).toBeLessThan(1);
        expect(Math.abs(previewBox.left - mainBox.right)).toBeLessThanOrEqual(1);
        await expect(main).toHaveCSS('border-right-style', 'solid');
        await expect(page.getByRole('heading', { name: 'Schrijven', exact: true })).toBeVisible();
    });

    test('post editor sections and controls stay within the mobile viewport', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await openFixturePostEditor(page);

        const metaBox = page.locator('.teksttv-meta-box');
        const [overflow, bounds, panelBounds] = await Promise.all([
            metaBox.evaluate((element) => element.scrollWidth - element.clientWidth),
            metaBox.evaluate((element) => element.getBoundingClientRect().toJSON()),
            metaBox
                .locator(
                    '.teksttv-content-section, .teksttv-editor-preview, .teksttv-media-section, .teksttv-collapsible',
                )
                .evaluateAll((panels) => panels.map((panel) => panel.getBoundingClientRect().toJSON())),
        ]);
        expect(overflow).toBeLessThanOrEqual(1);
        expect(panelBounds.every((panel) => panel.left >= bounds.left && panel.right <= bounds.right + 1)).toBe(true);

        const imageCards = page.locator('.teksttv-image-card');
        await expect(imageCards).toHaveCount(3);
        const cardWidths = await imageCards.evaluateAll((cards) =>
            cards.map((card) => card.getBoundingClientRect().width),
        );
        expect(cardWidths.every((width) => width >= 80)).toBe(true);

        await expect(page.locator('#teksttv-active')).toHaveAccessibleName(/Toon op Tekst TV/);
        await expect(page.locator('#teksttv-add-images .dashicons')).toHaveCount(0);
        await expect(page.locator('#teksttv_content-html')).toHaveCount(0);
        await expect(page.getByText('Nieuwe slide', { exact: true })).toBeVisible();
        const editorToolbarHeight = await page
            .locator('#wp-teksttv_content-editor-container .mce-toolbar-grp')
            .evaluate((element) => element.getBoundingClientRect().height);
        expect(editorToolbarHeight).toBeLessThanOrEqual(60);
        const controlHeights = await Promise.all([
            page.locator('#teksttv-title').evaluate((element) => element.getBoundingClientRect().height),
            page.locator('#teksttv-add-images').evaluate((element) => element.getBoundingClientRect().height),
        ]);
        expect(controlHeights.every((height) => height >= 40)).toBe(true);

        const titleFooter = page.locator('.teksttv-title-footer');
        await expect(titleFooter).toBeHidden();
        await page.locator('#teksttv-title').fill('Korte kop');
        await expect(titleFooter).toBeVisible();
    });

    test('post editor updates the word count from TinyMCE keyup', async ({ page }) => {
        await openFixturePostEditor(page);
        const wordCount = page.locator('#teksttv-wordcount');

        await page.waitForFunction(() => Boolean(window.tinymce?.get('teksttv_content')));
        await expect(wordCount).toHaveText(/^5(?: \/ \d+)? woorden$/);

        // Let the initial 500 ms update and 400 ms debounce settle so only the
        // keyup below can produce the next count.
        await page.waitForTimeout(1_000);

        await page.evaluate(() => {
            // Avoid input/change/SetContent so this specifically covers the keyup fallback.
            const tinyMceEditor = window.tinymce?.get('teksttv_content');
            if (!tinyMceEditor) throw new Error('TinyMCE editor teksttv_content not found.');
            tinyMceEditor.setContent('<p>Twee woorden</p>', { no_events: true });
            tinyMceEditor.fire('keyup');
        });
        await expect(wordCount).toHaveText(/^2(?: \/ \d+)? woorden$/);
    });

    test('post editor exposes the preview enlargement control on keyboard focus', async ({ page }) => {
        await openFixturePostEditor(page);
        const enlarge = page.locator('#teksttv-preview-enlarge');
        await enlarge.focus();
        await expect(enlarge).toBeFocused();
        await expect(enlarge).toHaveCSS('opacity', '1');
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
