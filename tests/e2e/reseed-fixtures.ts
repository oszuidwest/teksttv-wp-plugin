import { execSync } from 'node:child_process';

/** Restore the shared wp-env database to the deterministic E2E fixture state. */
export function reseedFixtures(): void {
    execSync('bun run test:e2e:fixtures', { stdio: 'inherit' });
}
