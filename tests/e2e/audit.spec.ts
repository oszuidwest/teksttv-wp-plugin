import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';
import { reseedFixtures } from './reseed-fixtures';

test.describe('AI audit statistics', () => {
    test.afterEach(() => {
        reseedFixtures();
    });

    test('represent all matching posts on every results page', async ({ page }) => {
        const output = execFileSync(
            'bun',
            ['x', 'wp-env', 'run', 'cli', 'wp', 'eval-file', 'wp-content/e2e/audit-stats.php'],
            { encoding: 'utf8', timeout: 120_000 },
        );
        expect(output).toContain('audit-stats-ok count=51');

        const assertStats = async () => {
            const cards = page.locator('.teksttv-audit-stat-number');
            await expect(cards).toHaveCount(4);
            await expect(cards.nth(0)).toHaveText('51');
            await expect(cards.nth(1)).toHaveText('0%');
            await expect(cards.nth(2)).toHaveText('2%');
            await expect(cards.nth(3)).toHaveText('2%');
        };

        await page.goto('/wp-admin/admin.php?page=teksttv-audit');
        await assertStats();
        await expect(page.locator('.teksttv-audit-table tbody tr')).toHaveCount(50);

        await page.goto('/wp-admin/admin.php?page=teksttv-audit&paged=2');
        await assertStats();
        await expect(page.locator('.teksttv-audit-table tbody tr')).toHaveCount(1);
    });
});
