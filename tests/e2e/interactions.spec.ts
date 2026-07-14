import { type Locator, type Page, expect, test } from '@playwright/test';
import { login } from './helpers';

const LOOP_URL = '/wp-admin/admin.php?page=teksttv-loop-tv1';

async function addLoopBlock(page: Page, type: string): Promise<Locator> {
    const blocks = page.locator('#teksttv-blocks > .teksttv-block');
    const previousCount = await blocks.count();

    await page.locator('#teksttv-add-block-toggle').click();
    await page.locator(`#teksttv-add-block-menu button[data-type="${type}"]`).click();

    await expect(blocks).toHaveCount(previousCount + 1);
    return blocks.last();
}

async function addTickerBlock(page: Page, type: string): Promise<Locator> {
    const ticker = page.locator('#teksttv-ticker > .teksttv-block');
    const previousCount = await ticker.count();

    await page.locator('#teksttv-add-ticker-toggle').click();
    await page.locator(`#teksttv-add-ticker-menu button[data-type="${type}"]`).click();

    await expect(ticker).toHaveCount(previousCount + 1);
    return ticker.last();
}

async function expectSequentialNames(root: Locator, itemSelector: string, prefix: string): Promise<void> {
    const items = root.locator(itemSelector);
    const count = await items.count();

    for (let index = 0; index < count; index++) {
        const names = await items
            .nth(index)
            .locator('input[name], select[name]')
            .evaluateAll((fields) => fields.map((field) => field.getAttribute('name')));

        expect(names.length, `${prefix}[${index}] should contain named fields`).toBeGreaterThan(0);
        for (const name of names) {
            expect(name, `field in item ${index} should use the DOM-order index`).toMatch(
                new RegExp(`^${prefix.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}\\[${index}\\]`),
            );
        }
    }
}

async function dragBlockToStart(page: Page, sourceBlock: Locator, targetBlock: Locator): Promise<void> {
    const source = await sourceBlock.locator('.teksttv-block-handle').boundingBox();
    const target = await targetBlock.locator('.teksttv-block-handle').boundingBox();
    if (!source || !target) throw new Error('Sortable handles must be visible before dragging.');

    await page.mouse.move(source.x + source.width / 2, source.y + source.height / 2);
    await page.mouse.down();
    await page.waitForTimeout(100);
    await page.mouse.move(target.x + target.width / 2, target.y + 2, { steps: 20 });
    await page.waitForTimeout(200);
    await page.mouse.up();
}

test.describe('admin interaction contracts', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, 'admin', 'password');
    });

    test('adds every registered loop block expanded at the next free index', async ({ page }) => {
        await page.goto(LOOP_URL);

        const types = await page
            .locator('#teksttv-add-block-menu button[data-type]')
            .evaluateAll((buttons) => buttons.map((button) => button.getAttribute('data-type')));
        expect(types).toEqual(['articles', 'image', 'iframe', 'campaign', 'weather']);

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

    test('removes a middle loop block and reindexes every remaining field', async ({ page }) => {
        await page.goto(LOOP_URL);
        await addLoopBlock(page, 'image');
        await addLoopBlock(page, 'iframe');

        const blocks = page.locator('#teksttv-blocks > .teksttv-block');
        await blocks.nth(1).locator('.teksttv-remove-block').click();
        await expect(blocks).toHaveCount(2);
        await expect(blocks.nth(0)).toHaveAttribute('data-type', 'articles');
        await expect(blocks.nth(1)).toHaveAttribute('data-type', 'iframe');
        await expectSequentialNames(page.locator('#teksttv-blocks'), ':scope > .teksttv-block', 'teksttv_blocks');
    });

    test('reorders loop blocks by drag and reindexes names in the new DOM order', async ({ page }) => {
        await page.goto(LOOP_URL);
        await addLoopBlock(page, 'image');
        await addLoopBlock(page, 'iframe');

        const blocks = page.locator('#teksttv-blocks > .teksttv-block');
        await dragBlockToStart(page, blocks.nth(2), blocks.nth(0));

        await expect(blocks.nth(0)).toHaveAttribute('data-type', 'iframe');
        await expectSequentialNames(page.locator('#teksttv-blocks'), ':scope > .teksttv-block', 'teksttv_blocks');
    });

    test('shows and clears scheduling fields through the scheduling toggle', async ({ page }) => {
        await page.goto(LOOP_URL);

        const block = page.locator('#teksttv-blocks > .teksttv-block').first();
        await block.locator('.teksttv-block-header').click();
        const toggle = block.locator('.teksttv-scheduling-checkbox');
        const scheduling = block.locator('.teksttv-block-fields--scheduling');
        const startDate = scheduling.locator('input[type="date"]').first();

        await expect(toggle).not.toBeChecked();
        await expect(scheduling).toBeHidden();
        await toggle.check();
        await expect(scheduling).toBeVisible();
        await startDate.fill('2026-08-01');

        await toggle.uncheck();
        await expect(scheduling).toBeHidden();
        await expect(startDate).toHaveValue('');
        for (const day of await scheduling.locator('input[type="checkbox"]').all()) {
            await expect(day).toBeChecked();
        }
    });

    test('updates a block header summary from its data-summary field', async ({ page }) => {
        await page.goto(LOOP_URL);

        const articleBlock = page.locator('#teksttv-blocks > .teksttv-block[data-type="articles"]').first();
        await articleBlock.locator('.teksttv-block-header').click();
        await articleBlock.locator('input[name$="[count]"]').fill('17');
        await expect(articleBlock.locator('.teksttv-block-summary')).toContainText('17x');
    });

    test('persists registry-managed block values after saving and reloading', async ({ page }) => {
        await page.goto(LOOP_URL);

        const articleBlock = page.locator('#teksttv-blocks > .teksttv-block[data-type="articles"]').first();
        await articleBlock.locator('.teksttv-block-header').click();
        await articleBlock.locator('input[name$="[count]"]').fill('9');
        await articleBlock.locator('input[name$="[duration_text]"]').fill('23');

        let iframeBlock = page.locator('#teksttv-blocks > .teksttv-block[data-type="iframe"]').first();
        if ((await iframeBlock.count()) === 0) {
            iframeBlock = await addLoopBlock(page, 'iframe');
        } else if (!(await iframeBlock.locator('.teksttv-block-body').isVisible())) {
            await iframeBlock.locator('.teksttv-block-header').click();
        }
        await iframeBlock.locator('input[name$="[name]"]').fill('E2E dashboard');
        await iframeBlock.locator('input[name$="[url]"]').fill('https://example.test/dashboard');
        await iframeBlock.locator('input[name$="[duration]"]').fill('31');

        await Promise.all([
            page.waitForLoadState('domcontentloaded'),
            page.locator('form input[name="submit"]').click(),
        ]);
        await page.reload();

        const savedArticle = page.locator('#teksttv-blocks > .teksttv-block[data-type="articles"]').first();
        await expect(savedArticle.locator('input[name$="[count]"]')).toHaveValue('9');
        await expect(savedArticle.locator('input[name$="[duration_text]"]')).toHaveValue('23');

        const savedIframe = page.locator('#teksttv-blocks > .teksttv-block[data-type="iframe"]').first();
        await expect(savedIframe.locator('input[name$="[name]"]')).toHaveValue('E2E dashboard');
        await expect(savedIframe.locator('input[name$="[url]"]')).toHaveValue('https://example.test/dashboard');
        await expect(savedIframe.locator('input[name$="[duration]"]')).toHaveValue('31');
        await expectSequentialNames(page.locator('#teksttv-blocks'), ':scope > .teksttv-block', 'teksttv_blocks');
    });

    test('adds and removes ticker items while keeping all names sequential', async ({ page }) => {
        await page.goto(LOOP_URL);
        await addTickerBlock(page, 'ticker_text');
        await addTickerBlock(page, 'ticker_headlines');

        const ticker = page.locator('#teksttv-ticker > .teksttv-block');
        await expectSequentialNames(page.locator('#teksttv-ticker'), ':scope > .teksttv-block', 'teksttv_ticker');
        await ticker.nth(1).locator('.teksttv-remove-block').click();

        await expect(ticker).toHaveCount(2);
        await expect(ticker.nth(1)).toHaveAttribute('data-type', 'ticker_headlines');
        await expectSequentialNames(page.locator('#teksttv-ticker'), ':scope > .teksttv-block', 'teksttv_ticker');
    });

    test('adds and removes campaign and group rows and persists the remaining values', async ({ page }) => {
        await page.goto('/wp-admin/admin.php?page=teksttv-campaigns');

        const groups = page.locator('#teksttv-groups tbody > .teksttv-group-row');
        await page.locator('#teksttv-add-group').click();
        const addedGroup = groups.last();
        await addedGroup.locator('input[name$="[label]"]').fill('E2E Added Group');
        await expect(addedGroup.locator('input[name]').first()).toHaveAttribute(
            'name',
            /^teksttv_campaign_groups\[new-\d+\]\[id\]$/,
        );
        await groups.nth(1).locator('.teksttv-remove-group').click();
        await expect(groups).toHaveCount(2);

        const groupNames = await groups
            .locator('input[name]')
            .evaluateAll((fields) => fields.map((field) => field.getAttribute('name')));
        expect(groupNames).toEqual([
            'teksttv_campaign_groups[0][id]',
            'teksttv_campaign_groups[0][label]',
            'teksttv_campaign_groups[new-0][id]',
            'teksttv_campaign_groups[new-0][label]',
        ]);

        const campaigns = page.locator('#teksttv-campaigns > .teksttv-block');
        await page.locator('#teksttv-add-campaign').click();
        const addedCampaign = campaigns.last();
        await addedCampaign.locator('input[name$="[name]"]').fill('E2E Added Campaign');
        await addedCampaign.locator('input[name$="[duration]"]').fill('19');
        await campaigns.nth(1).locator('.teksttv-remove-block').click();

        await expect(campaigns).toHaveCount(2);
        await expectSequentialNames(page.locator('#teksttv-campaigns'), ':scope > .teksttv-block', 'teksttv_campaigns');
        await Promise.all([
            page.waitForLoadState('domcontentloaded'),
            page.locator('form input[name="submit"]').click(),
        ]);
        await page.reload();

        const savedGroupLabels = await page
            .locator('#teksttv-groups input[name$="[label]"]')
            .evaluateAll((inputs) => inputs.map((input) => (input as HTMLInputElement).value));
        expect(savedGroupLabels).toEqual(['E2E Seed Group Alpha', 'E2E Added Group']);
        const savedCampaignNames = await page
            .locator('#teksttv-campaigns input[name$="[name]"]')
            .evaluateAll((inputs) => inputs.map((input) => (input as HTMLInputElement).value));
        expect(savedCampaignNames).toEqual(['E2E Seed Campaign Alpha', 'E2E Added Campaign']);
        await expect(page.locator('#teksttv-campaigns input[name$="[duration]"]').last()).toHaveValue('19');
        await expectSequentialNames(page.locator('#teksttv-campaigns'), ':scope > .teksttv-block', 'teksttv_campaigns');
    });

    test('adds and removes channel rows and reindexes every remaining field', async ({ page }) => {
        await page.goto('/wp-admin/admin.php?page=teksttv-settings');

        const rows = page.locator('#teksttv-channels tbody > .teksttv-channel-row');
        await page.locator('#teksttv-add-channel').click();
        await rows.last().locator('input[name$="[slug]"]').fill('e2e-two');
        await rows.last().locator('input[name$="[label]"]').fill('E2E Two');
        await page.locator('#teksttv-add-channel').click();
        await rows.last().locator('input[name$="[slug]"]').fill('e2e-three');
        await rows.last().locator('input[name$="[label]"]').fill('E2E Three');

        await expectSequentialNames(page.locator('#teksttv-channels tbody'), ':scope > tr', 'teksttv_channels');
        await rows.nth(1).locator('.teksttv-remove-channel').click();

        await expect(rows).toHaveCount(2);
        await expect(rows.nth(1).locator('input[name$="[slug]"]')).toHaveValue('e2e-three');
        await expectSequentialNames(page.locator('#teksttv-channels tbody'), ':scope > tr', 'teksttv_channels');
    });
});
