import { type Page, expect } from '@playwright/test';

/** Log in through wp-login.php and wait for the admin dashboard. */
export async function login(page: Page, username: string, password: string): Promise<void> {
    await page.goto('/wp-login.php');
    await page.fill('#user_login', username);
    await page.fill('#user_pass', password);
    await Promise.all([page.waitForURL(/wp-admin/), page.click('#wp-submit')]);
    await expect(page.locator('#wpadminbar')).toBeVisible();
}
