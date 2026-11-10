import { defineConfig } from '@playwright/test';

/**
 * Browser and API smoke tests against a packaged Playground instance.
 */
export default defineConfig({
    testDir: './tests/e2e',
    timeout: 30_000,
    expect: { timeout: 10_000 },
    fullyParallel: false,
    // Each worker owns an isolated Playground; keep stateful tests within a file sequential.
    workers: 2,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    reporter: 'list',
    use: {
        trace: 'on-first-retry',
        // Headless Chromium needs explicit clipboard permission.
        permissions: ['clipboard-write'],
    },
    projects: [{ name: 'chromium', use: { browserName: 'chromium' } }],
});
