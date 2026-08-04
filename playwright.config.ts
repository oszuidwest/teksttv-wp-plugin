import { defineConfig } from '@playwright/test';

/**
 * Browser + API smoke suite for the packaged plugin. A worker-scoped fixture
 * starts WordPress Playground from blueprint.json and tears it down again.
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
        baseURL: 'http://127.0.0.1:8888',
        trace: 'on-first-retry',
        // Real browsers allow clipboard writes on a user gesture; headless
        // Chromium needs the permission granted explicitly.
        permissions: ['clipboard-write'],
    },
    projects: [{ name: 'chromium', use: { browserName: 'chromium' } }],
});
