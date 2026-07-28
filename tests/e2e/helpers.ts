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

    const toggle = page.locator(ui.toggle);
    if (await toggle.count()) {
        await toggle.click();
        await page.locator(`${ui.menu} button[data-type="${type}"]`).click();
    } else {
        // With exactly one registered ticker type the view renders a single
        // button instead of the dropdown (the loop always has a dropdown).
        await page.locator(`${ui.single}[data-type="${type}"]`).click();
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
    const form = page.locator(
        'form:has(input[name="teksttv_loop_nonce"]), form:has(input[name="teksttv_campaigns_nonce"])',
    );
    await form.locator('input[name="submit"]').click();
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
