/**
 * Stub a global in a test.
 *
 * Bun runs test files in a shared global scope without isolation, so a global
 * stubbed in one file leaks into the others. `Object.defineProperty` without an
 * explicit `writable: true` leaves the property non-writable, and a later file's
 * plain `globalThis.x = …` then throws "Attempted to assign to readonly
 * property". Which file wins the race depends on Bun's (non-deterministic) file
 * order, so the failure only showed up on some machines. Always defining the
 * property as writable + configurable keeps every stub reassignable.
 */
export function setGlobal(key: string, value: unknown): void {
    Object.defineProperty(globalThis, key, { value, writable: true, configurable: true });
}
