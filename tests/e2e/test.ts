import { type BrowserContext, test as base, expect } from '@playwright/test';
import type { RunCLIServer } from '@wp-playground/cli';
import { login } from './helpers';
import { E2E_VFS_PATH, PLAYGROUND_BASE_PORT, startPlayground } from './playground';

export type RunWordPressPHP = (code: string) => Promise<string>;
export type RunWordPressPHPFile = (file: string) => Promise<string>;

interface TestFixtures {
    runWordPressPHP: RunWordPressPHP;
    runWordPressPHPFile: RunWordPressPHPFile;
}

type StorageState = Awaited<ReturnType<BrowserContext['storageState']>>;

interface WorkerFixtures {
    adminStorageState: StorageState;
    playgroundServer: RunCLIServer;
}

/**
 * Start one collision-free Playground per Playwright worker.
 */
export const test = base.extend<TestFixtures, WorkerFixtures>({
    playgroundServer: [
        async ({ playwright: _playwright }, use, workerInfo) => {
            const server = await startPlayground(PLAYGROUND_BASE_PORT + workerInfo.workerIndex);

            try {
                await use(server);
            } finally {
                await server[Symbol.asyncDispose]();
            }
        },
        { scope: 'worker', auto: true },
    ],

    baseURL: async ({ playgroundServer }, use) => {
        await use(playgroundServer.serverUrl);
    },

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

    runWordPressPHPFile: async ({ runWordPressPHP }, use) => {
        await use((file: string) => runWordPressPHP(`require '${E2E_VFS_PATH}/${file}';`));
    },
});

export type { Locator, Page } from '@playwright/test';
export { expect };
