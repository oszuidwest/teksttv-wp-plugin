import { defineConfig } from '@playwright/test';

/**
 * Browser + API smoke suite for the plugin running inside a real WordPress
 * (see .wp-env.json). Assumes `wp-env` is running and fixtures are loaded;
 * the `test:e2e` npm script wires that up.
 */
export default defineConfig({
    testDir: './tests/e2e',
    timeout: 30_000,
    expect: { timeout: 10_000 },
    fullyParallel: false,
    workers: 1,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    reporter: 'list',
    use: {
        baseURL: process.env.WP_BASE_URL || 'http://localhost:8888',
        trace: 'on-first-retry',
    },
    projects: [{ name: 'chromium', use: { browserName: 'chromium' } }],
});
