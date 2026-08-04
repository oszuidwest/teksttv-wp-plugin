import type { RunWordPressPHP } from './test';

/** Restore the shared Playground database to the deterministic E2E fixture state. */
export async function reseedFixtures(runWordPressPHP: RunWordPressPHP): Promise<void> {
    const output = await runWordPressPHP("require '/wordpress/wp-content/e2e/fixtures.php';");
    if (!output.includes('fixtures-ok ')) {
        throw new Error('Fixture reseed did not complete successfully.');
    }
}
