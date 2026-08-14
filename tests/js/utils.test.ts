import { afterEach, describe, expect, test } from 'bun:test';
import { debounce, splitPages } from '../../resources/ts/modules/utils';
import { setGlobal } from './setGlobal';

const originalWindow = globalThis.window;

afterEach(() => {
    setGlobal('window', originalWindow);
});

describe('debounce', () => {
    test('uses native timers without requiring the WordPress Underscore snapshot', async () => {
        setGlobal('window', {
            setTimeout: globalThis.setTimeout,
        } as unknown as Window & typeof globalThis);
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

describe('splitPages', () => {
    test('keeps separator markup on one page when the feature is disabled', () => {
        const html = '<p>Pagina één</p><p>---</p><p>Pagina twee</p>';

        expect(splitPages(html, false)).toEqual([html]);
    });

    test('splits on a separator paragraph when enabled', () => {
        const html = '<p>Pagina één</p><p>---</p><p>Pagina twee</p>';

        expect(splitPages(html)).toEqual(['<p>Pagina één</p>', '<p>Pagina twee</p>']);
    });
});
