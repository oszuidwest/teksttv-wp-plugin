import { expect, test } from '@playwright/test';

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

    test('campaign layout uses one width contract and responsive field grid', async ({ page }) => {
        await page.goto('/wp-admin/admin.php?page=teksttv-campaigns');

        const groupPanel = page.locator('.teksttv-campaign-groups');
        const campaignList = page.locator('#teksttv-campaigns');
        await expect(groupPanel).toBeVisible();
        await expect(campaignList).toBeVisible();

        const desktopWidths = await Promise.all([
            groupPanel.evaluate((element) => element.getBoundingClientRect().width),
            campaignList.evaluate((element) => element.getBoundingClientRect().width),
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

    test('post editor hides AI controls when no provider connector is configured', async ({ page }) => {
        await page.goto('/wp-admin/edit.php');
        await page.getByRole('link', { name: 'TekstTV Smoke Post' }).first().click();
        await expect(page.locator('#teksttv_meta')).toBeAttached();
        await expect(page.locator('.teksttv-generate-btn')).toHaveCount(0);
    });
});
