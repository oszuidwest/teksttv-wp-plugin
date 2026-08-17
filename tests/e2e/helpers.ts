import { expect, type Locator, type Page } from '@playwright/test';

export async function getBrowserErrors(page: Page): Promise<string[]> {
    const [pageErrors, consoleMessages] = await Promise.all([page.pageErrors(), page.consoleMessages()]);
    return [
        ...pageErrors.map((error) => `pageerror: ${error.stack ?? error.message}`),
        ...consoleMessages
            .filter((message) => message.type() === 'error' || message.type() === 'assert')
            .map((message) => `console.${message.type()}: ${message.text()}`),
    ];
}

export async function login(page: Page, username: string, password: string): Promise<void> {
    await page.goto('/wp-login.php');
    await page.fill('#user_login', username);
    await page.fill('#user_pass', password);
    await page.locator('#wp-submit').click();
    await expect(page).toHaveURL((url) => url.pathname === '/wp-admin' || url.pathname.startsWith('/wp-admin/'));
    await expect(page.locator('#wpadminbar')).toBeVisible();
}

/** Open the seeded post and expose its classic meta boxes. */
export async function openFixturePostEditor(page: Page): Promise<void> {
    await page.goto('/wp-admin/edit.php');
    await page.getByRole('link', { name: 'TekstTV Smoke Post' }).first().click();
    await expect(page.locator('#teksttv_meta')).toBeAttached();

    // Fixtures disable onboarding before this control loads.
    const metaBoxesButton = page.getByRole('button', { name: 'Meta Boxes', exact: true });
    await expect(metaBoxesButton).toBeVisible();
    if ((await metaBoxesButton.getAttribute('aria-expanded')) !== 'true') await metaBoxesButton.press('Enter');
    await expect(metaBoxesButton).toHaveAttribute('aria-expanded', 'true');
    await expect(page.locator('#teksttv_meta')).toBeVisible();
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
        // One ticker type renders a button instead of a dropdown.
        await page.locator(`${ui.single}[data-type="${type}"]`).click();
    }

    await expect(blocks).toHaveCount(previousCount + 1);
    return blocks.last();
}

/**
 * Submit settings, then reload saved state without resubmitting.
 */
export async function submitAndReload(page: Page): Promise<void> {
    const form = page.locator(
        'form:has(input[name="teksttv_loop_nonce"]), form:has(input[name="teksttv_commercials_nonce"])',
    );
    await form.locator('input[name="submit"]').click();
    await expect(page.locator('.notice-success').first()).toBeVisible();
    await page.goto(page.url());
}

export function addLoopBlock(page: Page, type: string): Promise<Locator> {
    return addBlock(page, 'loop', type);
}

export function addTickerBlock(page: Page, type: string): Promise<Locator> {
    return addBlock(page, 'ticker', type);
}
