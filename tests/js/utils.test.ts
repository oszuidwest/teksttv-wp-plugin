import { afterEach, describe, expect, test } from 'bun:test';
import { debounce } from '../../resources/ts/modules/utils';

const originalWindow = globalThis.window;

afterEach(() => {
    globalThis.window = originalWindow;
});

describe('debounce', () => {
    test('uses native timers without requiring the WordPress Underscore snapshot', async () => {
        globalThis.window = {
            setTimeout: globalThis.setTimeout,
        } as unknown as Window & typeof globalThis;
        let calls = 0;
        const debounced = debounce(() => {
            calls++;
        }, 5);

        debounced();
        debounced();
        await new Promise((resolve) => setTimeout(resolve, 20));

        expect(calls).toBe(1);
    });
});
