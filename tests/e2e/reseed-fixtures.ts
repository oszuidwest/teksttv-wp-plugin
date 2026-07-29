import { execFileSync } from 'node:child_process';

/** Restore the shared wp-env database to the deterministic E2E fixture state. */
export function reseedFixtures(): void {
    const output = execFileSync('bun', ['run', 'test:e2e:fixtures'], {
        encoding: 'utf8',
        stdio: ['inherit', 'pipe', 'inherit'],
        timeout: 120_000,
    });
    process.stdout.write(output);
    if (!output.includes('fixtures-ok ')) {
        throw new Error('Fixture reseed did not complete successfully.');
    }
}
