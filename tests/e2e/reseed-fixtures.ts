import type { RunWordPressPHPFile } from './test';

/**
 * Restore the shared Playground database to the deterministic E2E fixture
 * state. Takes the fixture object so specs can pass it straight to
 * `test.afterEach(reseedFixtures)`.
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
