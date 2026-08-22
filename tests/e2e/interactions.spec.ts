import { addLoopBlock, addTickerBlock, submitAndReload } from './helpers';
import { reseedFixtures } from './reseed-fixtures';
import { expect, type Locator, type Page, test } from './test';

const LOOP_URL = '/wp-admin/admin.php?page=teksttv-loop-tv1';

async function expectSequentialNames(root: Locator, itemSelector: string, prefix: string): Promise<void> {
    // Read ordered field names in one browser round trip.
    const itemNames = await root
        .locator(itemSelector)
        .evaluateAll((items) =>
            items.map((item) =>
                Array.from(item.querySelectorAll('input[name], select[name], textarea[name], [data-name]')).map(
                    (field) => field.getAttribute('name') ?? field.getAttribute('data-name'),
                ),
            ),
        );

    expect(itemNames.length, `${prefix} should contain at least one item`).toBeGreaterThan(0);
    itemNames.forEach((names, index) => {
        expect(names.length, `${prefix}[${index}] should contain named fields`).toBeGreaterThan(0);
        for (const name of names) {
            expect(name, `field in item ${index} should use the DOM-order index`).toMatch(
                new RegExp(`^${prefix}\\[${index}\\]`),
            );
        }
    });
}

// SortableJS needs intermediate mousemoves that dragTo() omits.
async function dragBlockToStart(page: Page, sourceBlock: Locator, targetBlock: Locator): Promise<void> {
    const source = await sourceBlock.locator('.teksttv-block-handle').boundingBox();
    const target = await targetBlock.boundingBox();
    if (!source || !target) throw new Error('Sortable handles must be visible before dragging.');

    await page.mouse.move(source.x + source.width / 2, source.y + source.height / 2);
    await page.mouse.down();
    await page.waitForTimeout(100);
    await page.mouse.move(target.x + Math.min(target.width / 2, 80), target.y + 1, { steps: 20 });
    await page.waitForTimeout(200);
    await page.mouse.up();
}

async function openBlockActions(block: Locator): Promise<void> {
    const actions = block.locator('.teksttv-block-actions');
    if ((await actions.getAttribute('open')) === null) await actions.locator('.teksttv-block-actions-toggle').click();
    await expect(actions).toHaveAttribute('open', '');
}

test.describe('admin interaction contracts', () => {
    test('keeps the block name visible when its summary is long', async ({ page }) => {
        await page.setViewportSize({ width: 800, height: 844 });
        await page.goto(LOOP_URL);

        const articleBlock = page.locator('#teksttv-blocks > .teksttv-block[data-type="articles"]').first();
        const title = articleBlock.locator('.teksttv-block-title');
        const summary = articleBlock.locator('.teksttv-block-summary');
        await summary.evaluate((element) => {
            element.textContent =
                '15x · Breda, Essen, Etten-Leur, Halderberge, Kalmthout, Moerdijk, Rucphen, West-Brabant, Zundert';
        });

        await expect(title).toHaveText('Artikelen');
        expect(await title.evaluate((element) => element.scrollWidth <= element.clientWidth)).toBe(true);
        expect(await summary.evaluate((element) => element.scrollWidth > element.clientWidth)).toBe(true);
    });

    test('adds every registered loop block expanded at the next free index', async ({ page }) => {
        await page.goto(LOOP_URL);

        const addToggle = page.locator('#teksttv-add-block-toggle');
        const addMenu = page.locator('#teksttv-add-block-menu');
        await expect(addToggle).not.toHaveAttribute('aria-haspopup', 'menu');
        await expect(addMenu).not.toHaveAttribute('role', 'menu');
        await expect(addMenu.locator('button').first()).not.toHaveAttribute('role', 'menuitem');

        const types = await page
            .locator('#teksttv-add-block-menu button[data-type]')
            .evaluateAll((buttons) => buttons.map((button) => button.getAttribute('data-type')));
        // Require built-ins without constraining add-ons or order.
        expect(types).toEqual(expect.arrayContaining(['articles', 'image', 'iframe', 'commercial', 'weather']));

        await addToggle.click();
        await addMenu.locator('button').last().focus();
        await page.keyboard.press('Tab');
        await expect(addMenu).toBeHidden();
        await expect(addToggle.locator('..')).not.toHaveAttribute('open', '');

        await addToggle.click();
        await addMenu.locator('button').first().focus();
        await page.keyboard.press('Escape');
        await expect(addMenu).toBeHidden();
        await expect(addToggle).toBeFocused();

        const initialCount = await page.locator('#teksttv-blocks > .teksttv-block').count();
        for (const [offset, type] of types.entries()) {
            if (!type) throw new Error('Registered block menu entries must expose data-type.');
            const block = await addLoopBlock(page, type);
            await expect(block).toHaveAttribute('data-type', type);
            await expect(block).toHaveClass(/is-expanded/);
            await expect(block.locator('.teksttv-block-body')).toBeVisible();

            const names = await block
                .locator('input[name], select[name]')
                .evaluateAll((fields) => fields.map((field) => field.getAttribute('name')));
            expect(names.length).toBeGreaterThan(0);
            for (const name of names) {
                expect(name).toMatch(new RegExp(`^teksttv_blocks\\[${initialCount + offset}\\]`));
            }
        }
    });

    test('supports keyboard accordions and preserves focus while adding and removing blocks', async ({ page }) => {
        await page.goto(LOOP_URL);

        const blocks = page.locator('#teksttv-blocks > .teksttv-block');
        const initialCount = await blocks.count();
        const firstBlock = blocks.first();
        const firstToggle = firstBlock.locator('.teksttv-block-toggle-control');

        await expect(firstToggle).toHaveAttribute('aria-expanded', 'false');
        const controlledBodyId = await firstToggle.getAttribute('aria-controls');
        expect(controlledBodyId).not.toBeNull();
        await expect(firstBlock.locator('.teksttv-block-body')).toHaveAttribute('id', controlledBodyId as string);
        await firstToggle.focus();
        await page.keyboard.press('Enter');
        await expect(firstToggle).toHaveAttribute('aria-expanded', 'true');
        await expect(firstBlock.locator('.teksttv-block-body')).toBeVisible();
        await page.keyboard.press('Space');
        await expect(firstToggle).toHaveAttribute('aria-expanded', 'false');
        await expect(firstBlock.locator('.teksttv-block-body')).toBeHidden();

        // A stale collapse must not hide a reopened block.
        await firstToggle.click();
        await page.waitForTimeout(175);
        await firstToggle.evaluate((toggle) => {
            if (!(toggle instanceof HTMLButtonElement)) return;
            toggle.click();
            window.setTimeout(() => toggle.click(), 25);
        });
        await page.waitForTimeout(200);
        await expect(firstToggle).toHaveAttribute('aria-expanded', 'true');
        await expect(firstBlock.locator('.teksttv-block-body')).toBeVisible();

        await addLoopBlock(page, 'image');
        await expect(blocks.nth(initialCount).locator('.teksttv-block-toggle-control')).toBeFocused();
        await openBlockActions(blocks.nth(initialCount));
        await expect(blocks.nth(initialCount).locator('.teksttv-remove-block')).toHaveAccessibleName(/verwijder blok/i);

        await addLoopBlock(page, 'iframe');
        await expect(blocks.nth(initialCount + 1).locator('.teksttv-block-toggle-control')).toBeFocused();
        await openBlockActions(blocks.nth(initialCount));
        await blocks.nth(initialCount).locator('.teksttv-remove-block').click();

        await expect(blocks).toHaveCount(initialCount + 1);
        await expect(blocks.nth(initialCount).locator('.teksttv-block-toggle-control')).toBeFocused();
        const controlledIds = await blocks
            .locator('.teksttv-block-toggle-control')
            .evaluateAll((toggles) => toggles.map((toggle) => toggle.getAttribute('aria-controls')));
        expect(new Set(controlledIds).size).toBe(controlledIds.length);
    });

    test('keeps keyboard undo persistent and restores focus to the removed block', async ({ page }) => {
        await page.goto(LOOP_URL);
        await addLoopBlock(page, 'image');

        const imageBlock = page.locator('#teksttv-blocks > .teksttv-block[data-type="image"]').last();
        await openBlockActions(imageBlock);
        const remove = imageBlock.locator('.teksttv-remove-block');
        await remove.focus();
        await page.keyboard.press('Enter');

        const snackbar = page.locator('#teksttv-snackbar');
        const undo = snackbar.locator('.teksttv-snackbar-action');
        await expect(imageBlock).toHaveCount(0);
        await expect(snackbar).toBeVisible();
        await expect(undo).toBeFocused();
        await page.clock.fastForward(8_100);
        await expect(snackbar).toBeVisible();

        await page.keyboard.press('Enter');
        const restored = page.locator('#teksttv-blocks > .teksttv-block[data-type="image"]').last();
        await expect(restored).toHaveCount(1);
        await expect(restored.locator('.teksttv-block-toggle-control')).toBeFocused();
    });

    test('closes block actions when keyboard focus leaves the menu', async ({ page }) => {
        await page.goto(LOOP_URL);
        const block = page.locator('#teksttv-blocks > .teksttv-block').first();
        await openBlockActions(block);

        const actions = block.locator('.teksttv-block-actions');
        await block.locator('.teksttv-remove-block').focus();
        await page.keyboard.press('Tab');
        await expect(actions).not.toHaveAttribute('open', '');
    });

    test('shows the shared empty state again after removing the last workbench item', async ({ page }) => {
        await page.goto(LOOP_URL);

        for (const rootId of ['#teksttv-blocks', '#teksttv-ticker']) {
            const root = page.locator(rootId);
            const removeButtons = root.locator('.teksttv-remove-block');
            while ((await removeButtons.count()) > 0) {
                const previousCount = await removeButtons.count();
                await removeButtons.first().dispatchEvent('click');
                await expect(removeButtons).toHaveCount(previousCount - 1);
            }
            const emptyState = root.locator(':scope > .teksttv-empty-state');
            await expect(emptyState).toBeVisible();
            await expect(emptyState.locator('button')).toHaveCount(0);
        }

        await addLoopBlock(page, 'articles');
        await expect(page.locator('#teksttv-blocks > .teksttv-empty-state')).toBeHidden();
        await addTickerBlock(page, 'ticker_text');
        await expect(page.locator('#teksttv-ticker > .teksttv-empty-state')).toBeHidden();
    });

    test('removes, restores and reindexes a middle loop block through the snackbar', async ({ page }) => {
        await page.goto(LOOP_URL);
        await addLoopBlock(page, 'image');
        await addLoopBlock(page, 'iframe');

        const blocks = page.locator('#teksttv-blocks > .teksttv-block');
        await openBlockActions(blocks.nth(1));
        await blocks.nth(1).locator('.teksttv-remove-block').click();
        await expect(blocks).toHaveCount(2);
        await expect(blocks.nth(0)).toHaveAttribute('data-type', 'articles');
        await expect(blocks.nth(1)).toHaveAttribute('data-type', 'iframe');
        await expect(page.locator('#teksttv-snackbar')).toContainText('Afbeelding verwijderd.');

        await page.locator('.teksttv-snackbar-action').click();
        await expect(blocks).toHaveCount(3);
        await expect(blocks.nth(1)).toHaveAttribute('data-type', 'image');
        await expect(blocks.nth(1).locator('.teksttv-block-toggle-control')).toBeFocused();
        await expectSequentialNames(page.locator('#teksttv-blocks'), ':scope > .teksttv-block', 'teksttv_blocks');

        await openBlockActions(blocks.nth(1));
        await blocks.nth(1).locator('.teksttv-remove-block').click();
        await expect(blocks).toHaveCount(2);
        await expectSequentialNames(page.locator('#teksttv-blocks'), ':scope > .teksttv-block', 'teksttv_blocks');
    });

    test('reorders loop blocks by drag and reindexes names in the new DOM order', async ({ page }) => {
        await page.goto(LOOP_URL);
        await addLoopBlock(page, 'image');
        await addLoopBlock(page, 'iframe');

        const blocks = page.locator('#teksttv-blocks > .teksttv-block');
        await blocks.nth(1).locator('.teksttv-block-toggle-control').click();
        await blocks.nth(2).locator('.teksttv-block-toggle-control').click();
        await expect(blocks.nth(2).locator('.teksttv-block-body')).toBeHidden();
        await dragBlockToStart(page, blocks.nth(2), blocks.nth(0));

        await expect(blocks.nth(0)).toHaveAttribute('data-type', 'iframe');
        await expectSequentialNames(page.locator('#teksttv-blocks'), ':scope > .teksttv-block', 'teksttv_blocks');
    });

    test('reorders blocks by keyboard and keeps field labels connected', async ({ page }) => {
        await page.goto(LOOP_URL);
        await addLoopBlock(page, 'iframe');

        const blocks = page.locator('#teksttv-blocks > .teksttv-block');
        const iframe = page.locator('#teksttv-blocks > .teksttv-block[data-type="iframe"]').last();
        await openBlockActions(iframe);
        await iframe.locator('.teksttv-move-block-up').focus();
        await page.keyboard.press('Enter');

        await expect(blocks.first()).toHaveAttribute('data-type', 'iframe');
        await expect(blocks.first().locator('.teksttv-move-block-up')).toBeDisabled();
        await expectSequentialNames(page.locator('#teksttv-blocks'), ':scope > .teksttv-block', 'teksttv_blocks');

        const brokenLabels = await page.locator('#teksttv-blocks .teksttv-field > label[for]').evaluateAll(
            (labels) =>
                labels.filter((label) => {
                    const control = (label as HTMLLabelElement).control;
                    return !control || control.closest('.teksttv-field') !== label.parentElement;
                }).length,
        );
        expect(brokenLabels).toBe(0);
    });

    test('shows and clears scheduling fields through the scheduling toggle', async ({ page }) => {
        await page.goto(LOOP_URL);

        const block = page.locator('#teksttv-blocks > .teksttv-block').first();
        await block.locator('.teksttv-block-toggle-control').click();
        const toggle = block.locator('.teksttv-scheduling-checkbox');
        const scheduling = block.locator('.teksttv-field-grid--scheduling');
        const startDate = scheduling.locator('input[type="date"]').first();

        await expect(toggle).not.toBeChecked();
        await expect(scheduling).toBeHidden();
        await toggle.check();
        await expect(scheduling).toBeVisible();
        const firstDay = scheduling.locator('input[type="checkbox"]').first();
        await firstDay.focus();
        await expect(firstDay).toBeFocused();
        await page.keyboard.press('Space');
        await expect(firstDay).not.toBeChecked();
        await startDate.fill('2026-08-01');

        await toggle.uncheck();
        await expect(scheduling).toBeHidden();
        await expect(startDate).toHaveValue('');
        const days = scheduling.locator('input[type="checkbox"]');
        await expect(days).toHaveCount(7);
        for (const day of await days.all()) {
            await expect(day).toBeChecked();
        }
    });

    test('updates a block header summary from its data-summary field', async ({ page }) => {
        await page.goto(LOOP_URL);

        const articleBlock = page.locator('#teksttv-blocks > .teksttv-block[data-type="articles"]').first();
        await articleBlock.locator('.teksttv-block-toggle-control').click();
        await articleBlock.locator('input[name$="[count]"]').fill('17');
        await expect(articleBlock.locator('.teksttv-block-summary')).toContainText('17x');
    });

    test('adds and removes ticker items while keeping all names sequential', async ({ page }) => {
        await page.goto(LOOP_URL);
        await addTickerBlock(page, 'ticker_text');
        await addTickerBlock(page, 'ticker_headlines');

        const ticker = page.locator('#teksttv-ticker > .teksttv-block');
        await expectSequentialNames(page.locator('#teksttv-ticker'), ':scope > .teksttv-block', 'teksttv_ticker');
        await openBlockActions(ticker.nth(1));
        await ticker.nth(1).locator('.teksttv-remove-block').click();

        await expect(ticker).toHaveCount(2);
        await expect(ticker.nth(1)).toHaveAttribute('data-type', 'ticker_headlines');
        await expectSequentialNames(page.locator('#teksttv-ticker'), ':scope > .teksttv-block', 'teksttv_ticker');
    });

    test('adds and removes channel rows and reindexes every remaining field', async ({ page }) => {
        await page.goto('/wp-admin/admin.php?page=teksttv-settings');

        const rows = page.locator('#teksttv-channels tbody > .teksttv-channel-row');
        await expect(rows.first().locator('.teksttv-remove-channel')).toHaveClass(/button-link-delete/);
        await expect(rows.first().locator('.teksttv-copy-endpoint')).toHaveAttribute(
            'data-endpoint',
            /\/wp-json\/teksttv\/v1\/slides\?channel=tv1$/,
        );
        await rows.first().locator('.teksttv-copy-endpoint').click();
        await expect(rows.first().locator('.teksttv-copy-endpoint-label')).toHaveText('Gekopieerd!');
        await page.locator('#teksttv-add-channel').click();
        await expect(rows.last().locator('.teksttv-remove-channel')).toHaveClass(/button-link-delete/);
        await expect(rows.last().locator('input[name$="[slug]"]')).toBeFocused();
        await rows.last().locator('input[name$="[slug]"]').fill('E2E two');
        await expect(rows.last().locator('.teksttv-copy-endpoint')).toBeDisabled();
        await rows.last().locator('input[name$="[slug]"]').fill('e2e_two');
        await rows.last().locator('input[name$="[label]"]').fill('E2E Two');
        const copyEndpoint = rows.last().locator('.teksttv-copy-endpoint');
        await expect(copyEndpoint).toBeEnabled();
        await expect(copyEndpoint).toHaveAttribute('data-endpoint', /\/wp-json\/teksttv\/v1\/slides\?channel=e2e_two$/);
        await page.locator('#teksttv-add-channel').click();
        await expect(rows.last().locator('input[name$="[slug]"]')).toBeFocused();
        await rows.last().locator('input[name$="[slug]"]').fill('e2e-three');
        await rows.last().locator('input[name$="[label]"]').fill('E2E Three');

        await expectSequentialNames(
            page.locator('#teksttv-channels tbody'),
            ':scope > .teksttv-channel-row',
            'teksttv_channels',
        );
        await rows.nth(1).locator('.teksttv-remove-channel').click();

        await expect(rows).toHaveCount(2);
        await expect(rows.nth(1).locator('input[name$="[slug]"]')).toHaveValue('e2e-three');
        await expect(rows.nth(1).locator('input[name$="[slug]"]')).toBeFocused();
        await expect(page.locator('#teksttv-snackbar')).toContainText('Kanaal verwijderd.');
        await page.locator('.teksttv-snackbar-action').click();
        await expect(rows).toHaveCount(3);
        await expect(rows.nth(1).locator('input[name$="[slug]"]')).toHaveValue('e2e_two');
        await expect(rows.nth(1).locator('input[name$="[slug]"]')).toBeFocused();

        await rows.nth(1).locator('.teksttv-remove-channel').click();
        await expect(rows).toHaveCount(2);
        await expectSequentialNames(
            page.locator('#teksttv-channels tbody'),
            ':scope > .teksttv-channel-row',
            'teksttv_channels',
        );
    });

    test('keeps management actions reachable without horizontal overflow on mobile', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });

        await page.goto('/wp-admin/admin.php?page=teksttv-settings');
        await expect(page.locator('#teksttv-channels .teksttv-copy-endpoint').first()).toBeVisible();
        await expect(page.locator('#teksttv-channels .teksttv-remove-channel').first()).toBeVisible();
        const settingsOverflow = await page
            .locator('.teksttv-settings-form')
            .evaluate((form) => form.scrollWidth - form.clientWidth);
        expect(settingsOverflow).toBeLessThanOrEqual(1);

        await page.goto('/wp-admin/admin.php?page=teksttv-commercials');
        await expect(page.locator('#submit')).toBeVisible();
        const campaignsOverflow = await page
            .locator('form.teksttv-admin-column')
            .evaluate((form) => form.scrollWidth - form.clientWidth);
        expect(campaignsOverflow).toBeLessThanOrEqual(1);
    });

    test('warns before leaving a form with unsaved changes', async ({ page }) => {
        const settingsUrl = '/wp-admin/admin.php?page=teksttv-settings';
        await page.goto(settingsUrl);
        const duration = page.locator('input[name="teksttv_duration_text"]');
        await duration.click();
        await duration.fill('41');

        const dialogPromise = page.waitForEvent('dialog');
        const navigationPromise = page.goto(LOOP_URL).catch(() => null);
        const dialog = await dialogPromise;
        expect(dialog.type()).toBe('beforeunload');
        await dialog.dismiss();
        await navigationPromise;
        await expect(page).toHaveURL((url) => `${url.pathname}${url.search}` === settingsUrl);
    });

    // Only stateful tests need fixture reseeding.
    test.describe('persisting saves', () => {
        test.afterEach(reseedFixtures);

        test('preserves an explicit no-weekdays schedule across saving and rendering', async ({ page }) => {
            await page.goto(LOOP_URL);

            let block = page.locator('#teksttv-blocks > .teksttv-block').first();
            await block.locator('.teksttv-block-toggle-control').click();
            await block.locator('.teksttv-scheduling-checkbox').check();

            let dayToggles = block.locator('.teksttv-field-grid--scheduling .teksttv-day-toggle');
            let days = dayToggles.locator('input[type="checkbox"]');
            await expect(days).toHaveCount(7);
            for (const toggle of await dayToggles.all()) {
                await toggle.locator('span').click();
            }

            await submitAndReload(page);

            block = page.locator('#teksttv-blocks > .teksttv-block').first();
            await expect(block.locator('.teksttv-scheduling-checkbox')).toBeChecked();
            dayToggles = block.locator('.teksttv-field-grid--scheduling .teksttv-day-toggle');
            days = dayToggles.locator('input[type="checkbox"]');
            await expect(days).toHaveCount(7);
            for (const day of await days.all()) {
                await expect(day).not.toBeChecked();
            }
        });

        test('persists registry-managed block values after saving and reloading', async ({ page }) => {
            await page.goto(LOOP_URL);

            const articleBlock = page.locator('#teksttv-blocks > .teksttv-block[data-type="articles"]').first();
            await articleBlock.locator('.teksttv-block-toggle-control').click();
            await articleBlock.locator('input[name$="[count]"]').fill('9');
            await articleBlock.locator('input[name$="[duration_text]"]').fill('23');

            let iframeBlock = page.locator('#teksttv-blocks > .teksttv-block[data-type="iframe"]').first();
            if ((await iframeBlock.count()) === 0) {
                iframeBlock = await addLoopBlock(page, 'iframe');
            } else if (!(await iframeBlock.locator('.teksttv-block-body').isVisible())) {
                await iframeBlock.locator('.teksttv-block-toggle-control').click();
            }
            await iframeBlock.locator('input[name$="[name]"]').fill('E2E dashboard');
            await iframeBlock.locator('input[name$="[url]"]').fill('https://example.test/dashboard');
            await iframeBlock.locator('input[name$="[duration]"]').fill('31');

            await submitAndReload(page);

            const savedArticle = page.locator('#teksttv-blocks > .teksttv-block[data-type="articles"]').first();
            await expect(savedArticle.locator('input[name$="[count]"]')).toHaveValue('9');
            await expect(savedArticle.locator('input[name$="[duration_text]"]')).toHaveValue('23');

            const savedIframe = page.locator('#teksttv-blocks > .teksttv-block[data-type="iframe"]').first();
            await expect(savedIframe.locator('input[name$="[name]"]')).toHaveValue('E2E dashboard');
            await expect(savedIframe.locator('input[name$="[url]"]')).toHaveValue('https://example.test/dashboard');
            await expect(savedIframe.locator('input[name$="[duration]"]')).toHaveValue('31');
            await expectSequentialNames(page.locator('#teksttv-blocks'), ':scope > .teksttv-block', 'teksttv_blocks');
        });

        // Prove emission first so suppression cannot pass vacuously.
        test('serves campaign slides for an assigned channel and none after unchecking every channel', async ({
            page,
        }) => {
            const fetchSlides = async (): Promise<Array<{ type?: string; title?: string }>> => {
                const response = await page.request.get('/wp-json/teksttv/v1/slides?channel=tv1');
                expect(response.ok()).toBe(true);
                return (await response.json()).slides;
            };

            // Single-channel state must come from storage, not a hidden default.
            await page.goto('/wp-admin/admin.php?page=teksttv-commercials');
            let campaign = page.locator('#teksttv-campaigns > .teksttv-block').first();
            await campaign.locator('.teksttv-block-header').click();
            let channelCheckboxes = campaign.locator('input[name$="[channels][]"]');
            await expect(channelCheckboxes).toHaveCount(1);
            await expect(channelCheckboxes).toBeChecked();

            await page.goto('/wp-admin/admin.php?page=teksttv-settings');
            const channelRows = page.locator('#teksttv-channels tbody > .teksttv-channel-row');
            await page.locator('#teksttv-add-channel').click();
            await channelRows.last().locator('input[name$="[slug]"]').fill('tv2');
            await channelRows.last().locator('input[name$="[label]"]').fill('TV 2');
            await page.locator('#submit').click();
            await expect(channelRows).toHaveCount(2);

            await page.goto(LOOP_URL);
            let commercialLoopBlock = await addLoopBlock(page, 'commercial');
            const commercialBlocksSelect = commercialLoopBlock.locator('select[name$="[commercial_block_ids][]"]');
            await commercialBlocksSelect.selectOption('e2e-group-alpha');
            await submitAndReload(page);

            commercialLoopBlock = page.locator('#teksttv-blocks > .teksttv-block[data-type="commercial"]').first();
            await expect(commercialLoopBlock.locator('select[name$="[commercial_block_ids][]"]')).toHaveValues([
                'e2e-group-alpha',
            ]);

            let slides = await fetchSlides();
            expect(
                slides.some((slide) => slide.type === 'commercial'),
                'an assigned campaign contributes a commercial slide',
            ).toBe(true);

            await page.goto('/wp-admin/admin.php?page=teksttv-commercials');
            campaign = page.locator('#teksttv-campaigns > .teksttv-block').first();
            await campaign.locator('.teksttv-block-header').click();

            channelCheckboxes = campaign.locator('input[name$="[channels][]"]');
            await expect(channelCheckboxes).toHaveCount(2);
            for (const checkbox of await channelCheckboxes.all()) {
                await checkbox.uncheck();
            }

            await submitAndReload(page);

            campaign = page.locator('#teksttv-campaigns > .teksttv-block').first();
            channelCheckboxes = campaign.locator('input[name$="[channels][]"]');
            await expect(channelCheckboxes).toHaveCount(2);
            for (const checkbox of await channelCheckboxes.all()) {
                await expect(checkbox).not.toBeChecked();
            }

            slides = await fetchSlides();
            expect(
                slides.some((slide) => slide.type === 'text' && slide.title === 'TekstTV Smoke Post'),
                'the loop still serves regular slides',
            ).toBe(true);
            expect(
                slides.some((slide) => slide.type === 'commercial'),
                'a campaign without channels stays inactive at runtime',
            ).toBe(false);
        });

        test('adds and removes campaign and commercial block rows and persists the remaining values', async ({
            page,
        }) => {
            await page.goto('/wp-admin/admin.php?page=teksttv-commercials');

            const commercialBlocks = page.locator('#teksttv-commercial-blocks tbody > .teksttv-commercial-block-row');
            await expect(commercialBlocks.first().locator('.teksttv-remove-commercial-block')).toHaveClass(
                /button-link-delete/,
            );
            await page.locator('#teksttv-add-commercial-block').click();
            const addedCommercialBlock = commercialBlocks.last();
            await expect(addedCommercialBlock.locator('.teksttv-remove-commercial-block')).toHaveClass(
                /button-link-delete/,
            );
            await expect(addedCommercialBlock.locator('input[name$="[label]"]')).toBeFocused();
            await addedCommercialBlock.locator('input[name$="[label]"]').fill('E2E Added Commercial Block');
            // The server mints IDs for new template rows.
            await expect(addedCommercialBlock.locator('input[name$="[id]"]')).toHaveValue('');
            await commercialBlocks.nth(1).locator('.teksttv-remove-commercial-block').click();
            await expect(commercialBlocks).toHaveCount(2);

            const commercialBlockFieldNames = await commercialBlocks
                .locator('input[name]')
                .evaluateAll((fields) => fields.map((field) => field.getAttribute('name')));
            expect(commercialBlockFieldNames).toEqual([
                'teksttv_commercial_blocks[0][id]',
                'teksttv_commercial_blocks[0][label]',
                'teksttv_commercial_blocks[1][id]',
                'teksttv_commercial_blocks[1][label]',
            ]);

            const campaigns = page.locator('#teksttv-campaigns > .teksttv-block');
            await page.locator('#teksttv-add-campaign').click();
            const addedCampaign = campaigns.last();
            await addedCampaign.locator('input[name$="[name]"]').fill('E2E Added Campaign');
            await addedCampaign.locator('input[name$="[duration]"]').fill('19');
            await openBlockActions(campaigns.nth(1));
            await campaigns.nth(1).locator('.teksttv-remove-block').click();

            await expect(campaigns).toHaveCount(2);
            await expectSequentialNames(
                page.locator('#teksttv-campaigns'),
                ':scope > .teksttv-block',
                'teksttv_campaigns',
            );
            await submitAndReload(page);

            const savedCommercialBlockLabels = await page
                .locator('#teksttv-commercial-blocks input[name$="[label]"]')
                .evaluateAll((inputs) => inputs.map((input) => (input as HTMLInputElement).value));
            expect(savedCommercialBlockLabels).toEqual([
                'E2E Seed Commercial Block Alpha',
                'E2E Added Commercial Block',
            ]);
            const savedCampaignNames = await page
                .locator('#teksttv-campaigns input[name$="[name]"]')
                .evaluateAll((inputs) => inputs.map((input) => (input as HTMLInputElement).value));
            expect(savedCampaignNames).toEqual(['E2E Seed Campaign Alpha', 'E2E Added Campaign']);
            await expect(page.locator('#teksttv-campaigns input[name$="[duration]"]').last()).toHaveValue('19');
            await expectSequentialNames(
                page.locator('#teksttv-campaigns'),
                ':scope > .teksttv-block',
                'teksttv_campaigns',
            );
        });
    });
});
