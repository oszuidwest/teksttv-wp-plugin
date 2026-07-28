import { execFileSync } from 'node:child_process';

/** Restore the shared wp-env database to the deterministic E2E fixture state. */
export function reseedFixtures(): void {
    execFileSync('bun', ['run', 'test:e2e:fixtures'], { stdio: 'inherit', timeout: 120_000 });
}
