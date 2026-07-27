import { type Locator, type Page, expect } from '@playwright/test';

/** Admin session saved by global-setup and loaded by every test (see playwright.config.ts). */
export const ADMIN_STORAGE_STATE = '.playwright/auth/admin.json';

/** Log in through wp-login.php and wait for the admin dashboard. */
export async function login(page: Page, username: string, password: string): Promise<void> {
    await page.goto('/wp-login.php');
    await page.fill('#user_login', username);
    await page.fill('#user_pass', password);
    await Promise.all([page.waitForURL(/wp-admin/), page.click('#wp-submit')]);
    await expect(page.locator('#wpadminbar')).toBeVisible();
}

const ADD_BLOCK_UI = {
    loop: {
        list: '#teksttv-blocks',
        toggle: '#teksttv-add-block-toggle',
        menu: '#teksttv-add-block-menu',
        single: '#teksttv-add-block-single',
    },
    ticker: {
        list: '#teksttv-ticker',
        toggle: '#teksttv-add-ticker-toggle',
        menu: '#teksttv-add-ticker-menu',
        single: '#teksttv-add-ticker-single',
    },
} as const;

async function addBlock(page: Page, kind: keyof typeof ADD_BLOCK_UI, type: string): Promise<Locator> {
    const ui = ADD_BLOCK_UI[kind];
    const blocks = page.locator(`${ui.list} > .teksttv-block`);
    const previousCount = await blocks.count();

    // With exactly one registered ticker type the view renders a single
    // button instead of the dropdown.
    const single = page.locator(`${ui.single}[data-type="${type}"]`);
    if ((await page.locator(ui.toggle).count()) === 0 && (await single.count()) > 0) {
        await single.click();
    } else {
        await page.locator(ui.toggle).click();
        await page.locator(`${ui.menu} button[data-type="${type}"]`).click();
    }

    await expect(blocks).toHaveCount(previousCount + 1);
    return blocks.last();
}

/**
 * Submit the page's settings form, wait for the save round-trip to finish
 * (the success notice renders on the response document), then perform a GET
 * so assertions run against freshly rendered saved state without resubmitting.
 */
export async function submitAndReload(page: Page): Promise<void> {
    await page.locator('form input[name="submit"]').click();
    await expect(page.locator('.notice-success').first()).toBeVisible();
    await page.goto(page.url());
}

/** Add a loop block via the add-block dropdown and return the new block. */
export function addLoopBlock(page: Page, type: string): Promise<Locator> {
    return addBlock(page, 'loop', type);
}

/** Add a ticker block via the add-ticker dropdown and return the new block. */
export function addTickerBlock(page: Page, type: string): Promise<Locator> {
    return addBlock(page, 'ticker', type);
}
