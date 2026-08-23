import type { RunWordPressPHPFile } from './test';

/**
 * Restore deterministic fixtures after stateful E2E tests.
 */
export async function reseedFixtures({
    runWordPressPHPFile,
}: {
    runWordPressPHPFile: RunWordPressPHPFile;
}): Promise<void> {
    const output = await runWordPressPHPFile('fixtures.php');
    if (!output.includes('fixtures-ok ')) {
        throw new Error('Fixture reseed did not complete successfully.');
    }
}
