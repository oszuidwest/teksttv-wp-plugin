import { readFile } from 'node:fs/promises';
import { type BrowserContext, test as base, expect } from '@playwright/test';
import { type RunCLIServer, runCLI } from '@wp-playground/cli';
import { login } from './helpers';

const PROJECT_ROOT = process.cwd();
const PLAYGROUND_PORT = 8888;
const PLAYGROUND_URL = `http://127.0.0.1:${PLAYGROUND_PORT}`;

export type RunWordPressPHP = (code: string) => Promise<string>;

interface TestFixtures {
    runWordPressPHP: RunWordPressPHP;
}

type StorageState = Awaited<ReturnType<BrowserContext['storageState']>>;

interface WorkerFixtures {
    adminStorageState: StorageState;
    playgroundServer: RunCLIServer;
}

/**
 * Start one disposable Playground per Playwright worker. The Blueprint owns
 * WordPress configuration and fixture setup; mounts expose only the packaged
 * plugin and E2E support files to the WebAssembly filesystem.
 */
export const test = base.extend<TestFixtures, WorkerFixtures>({
    playgroundServer: [
        async ({ playwright: _playwright }, use) => {
            const blueprint = JSON.parse(await readFile(`${PROJECT_ROOT}/blueprint.json`, 'utf8'));
            const server = await runCLI({
                command: 'server',
                blueprint,
                php: '8.3',
                port: PLAYGROUND_PORT,
                'site-url': PLAYGROUND_URL,
                workers: 6,
                wp: '7.0',
                mount: [
                    {
                        hostPath: `${PROJECT_ROOT}/release/teksttv`,
                        vfsPath: '/wordpress/wp-content/plugins/teksttv',
                    },
                    {
                        hostPath: `${PROJECT_ROOT}/tests/e2e`,
                        vfsPath: '/wordpress/wp-content/e2e',
                    },
                ],
            });

            try {
                await use(server);
            } finally {
                await server[Symbol.asyncDispose]();
            }
        },
        { scope: 'worker', auto: true },
    ],

    adminStorageState: [
        async ({ browser, playgroundServer }, use) => {
            const context = await browser.newContext({ baseURL: playgroundServer.serverUrl });
            const page = await context.newPage();
            await login(page, 'admin', 'password');
            const storageState = await context.storageState();
            await context.close();
            await use(storageState);
        },
        { scope: 'worker' },
    ],

    storageState: async ({ adminStorageState }, use) => {
        await use(adminStorageState);
    },

    runWordPressPHP: async ({ playgroundServer }, use) => {
        await use(async (code: string): Promise<string> => {
            const response = await playgroundServer.playground.run({
                code: `<?php require '/wordpress/wp-load.php';\n${code}`,
            });

            if (response.exitCode !== 0 || response.httpStatusCode >= 400) {
                throw new Error(response.errors || response.text || `PHP exited with code ${response.exitCode}.`);
            }

            return response.text;
        });
    },
});

export type { Locator, Page } from '@playwright/test';
export { expect };
