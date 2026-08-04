import { defineConfig } from '@playwright/test';
import { ADMIN_STORAGE_STATE } from './tests/e2e/helpers';

/**
 * Browser + API smoke suite for the plugin running inside a real WordPress
 * (see .wp-env.json). Assumes `wp-env` is running and fixtures are loaded;
 * the `test:e2e` npm script wires that up.
 */
export default defineConfig({
    testDir: './tests/e2e',
    globalSetup: './tests/e2e/global-setup.ts',
    timeout: 30_000,
    expect: { timeout: 10_000 },
    fullyParallel: false,
    workers: 1,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    reporter: 'list',
    use: {
        baseURL: process.env.WP_BASE_URL || 'http://localhost:8888',
        storageState: ADMIN_STORAGE_STATE,
        trace: 'on-first-retry',
        // Real browsers allow clipboard writes on a user gesture; headless
        // Chromium needs the permission granted explicitly.
        permissions: ['clipboard-write'],
    },
    projects: [{ name: 'chromium', use: { browserName: 'chromium' } }],
});
