import { execSync } from 'node:child_process';

/**
 * Reseed the wp-env fixtures before every run so the suite is idempotent:
 * the persistence tests save real option changes (e.g. an extra iframe block
 * in teksttv_loop_tv1) that would otherwise leak into the next run.
 */
export default function globalSetup(): void {
    execSync('bun run test:e2e:fixtures', { stdio: 'inherit' });
}
