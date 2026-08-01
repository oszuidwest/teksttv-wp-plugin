import { expect, test } from '@playwright/test';
import { runEvalFile } from './helpers';
import { reseedFixtures } from './reseed-fixtures';

test.describe('AI audit statistics', () => {
    test.afterEach(() => {
        reseedFixtures();
    });

    test('represent all matching posts on every results page', async ({ page }) => {
        const output = runEvalFile('audit-stats.php');
        expect(output).toContain('audit-stats-ok count=52');

        // 52 posts (one private, visible to the admin session), 1 of 52 bodies
        // edited: round(1 / 52 * 100) = 2%.
        const assertStats = async () => {
            const cards = page.locator('.teksttv-audit-stat-number');
            await expect(cards).toHaveCount(4);
            await expect(cards.nth(0)).toHaveText('52');
            await expect(cards.nth(1)).toHaveText('0%');
            await expect(cards.nth(2)).toHaveText('2%');
            await expect(cards.nth(3)).toHaveText('2%');
        };

        await page.goto('/wp-admin/admin.php?page=teksttv-audit');
        await assertStats();
        await expect(page.locator('.teksttv-audit-table tbody tr')).toHaveCount(50);

        await page.goto('/wp-admin/admin.php?page=teksttv-audit&paged=2');
        await assertStats();
        await expect(page.locator('.teksttv-audit-table tbody tr')).toHaveCount(2);
    });
});
