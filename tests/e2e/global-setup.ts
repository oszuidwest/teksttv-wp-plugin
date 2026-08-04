import { mkdirSync } from 'node:fs';
import { dirname } from 'node:path';
import { chromium, type FullConfig } from '@playwright/test';
import { ADMIN_STORAGE_STATE, login, runWp } from './helpers';
import { reseedFixtures } from './reseed-fixtures';

/**
 * Reseed the wp-env fixtures before every run so the suite is idempotent:
 * the persistence tests save real option changes (e.g. an extra iframe block
 * in teksttv_loop_tv1) that would otherwise leak into the next run.
 *
 * Then log in once as admin and save the session cookies; every test loads
 * them via `storageState` instead of repeating the wp-login round-trip
 * (role.spec.ts opts out to test its own user).
 */
export default async function globalSetup(config: FullConfig): Promise<void> {
    runWp('rewrite', 'structure', '/%postname%/');
    reseedFixtures();

    const { baseURL } = config.projects[0].use;
    mkdirSync(dirname(ADMIN_STORAGE_STATE), { recursive: true });
    const browser = await chromium.launch();
    try {
        const page = await browser.newPage({ baseURL });
        await login(page, 'admin', 'password');
        await page.context().storageState({ path: ADMIN_STORAGE_STATE });
    } finally {
        await browser.close();
    }
}
