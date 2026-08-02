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

        // The 52 AI posts plus 12 active human-written fixture posts are all
        // included. One of 64 bodies is edited: round(1 / 64 * 100) = 2%.
        const assertStats = async () => {
            const cards = page.locator('.teksttv-audit-stat-number');
            await expect(cards).toHaveCount(4);
            await expect(cards.nth(0)).toHaveText('64');
            await expect(cards.nth(1)).toHaveText('0%');
            await expect(cards.nth(2)).toHaveText('2%');
            await expect(cards.nth(3)).toHaveText('2%');
        };

        await page.goto('/wp-admin/admin.php?page=teksttv-audit');
        await assertStats();
        await expect(page.locator('.teksttv-audit-table tbody tr')).toHaveCount(50);

        await page.goto('/wp-admin/admin.php?page=teksttv-audit&paged=2');
        await assertStats();
        await expect(page.locator('.teksttv-audit-table tbody tr')).toHaveCount(14);

        await page.goto('/wp-admin/admin.php?page=teksttv-audit&generation_status=human');
        await expect(page.locator('.teksttv-audit-table tbody tr')).toHaveCount(12);
        await expect(page.locator('.teksttv-audit-table tbody tr').first()).toContainText('Geen AI');

        await page.goto('/wp-admin/admin.php?page=teksttv-audit&generation_status=ai_edited&change_band=extensive');
        await expect(page.locator('.teksttv-audit-table tbody tr')).toHaveCount(1);
        await expect(page.locator('.teksttv-audit-table tbody tr').first()).toContainText('100%');
        await expect(page.locator('.teksttv-audit-table tbody tr').first()).toContainText('admin');

        await page.goto('/wp-admin/admin.php?page=teksttv-audit&month=2026-08&generation_status=ai_unmodified');
        const nextPage = page.locator('.next.page-numbers');
        await expect(nextPage).toHaveAttribute('href', /month=2026-08/);
        await expect(nextPage).toHaveAttribute('href', /generation_status=ai_unmodified/);
    });
});
