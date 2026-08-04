import { readFile } from 'node:fs/promises';
import { type RunCLIServer, runCLI } from '@wp-playground/cli';

const PROJECT_ROOT = process.cwd();

export const PLAYGROUND_BASE_PORT = 8888;
export const E2E_VFS_PATH = '/wordpress/wp-content/e2e';

/**
 * Start a Playground server from blueprint.json. The Blueprint owns WordPress
 * configuration (including the PHP/WP version pins) and fixture setup; mounts
 * expose only the packaged plugin and E2E support files to the WebAssembly
 * filesystem. Both the Playwright worker fixture and `bun run env:start` go
 * through here so the test and debug environments cannot drift apart.
 */
export async function startPlayground(port = PLAYGROUND_BASE_PORT): Promise<RunCLIServer> {
    const blueprint = JSON.parse(await readFile(`${PROJECT_ROOT}/blueprint.json`, 'utf8'));
    return runCLI({
        command: 'server',
        blueprint,
        port,
        'site-url': `http://127.0.0.1:${port}`,
        workers: 6,
        mount: [
            {
                hostPath: `${PROJECT_ROOT}/release/teksttv`,
                vfsPath: '/wordpress/wp-content/plugins/teksttv',
            },
            {
                hostPath: `${PROJECT_ROOT}/tests/e2e`,
                vfsPath: E2E_VFS_PATH,
            },
        ],
    });
}
