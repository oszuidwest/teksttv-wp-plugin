import { readFile } from 'node:fs/promises';
import { type RunCLIServer, runCLI } from '@wp-playground/cli';

const PROJECT_ROOT = process.cwd();

export const PLAYGROUND_BASE_PORT = 8888;
export const E2E_VFS_PATH = '/wordpress/wp-content/e2e';

/**
 * Start the shared packaged-plugin Playground from blueprint.json.
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
