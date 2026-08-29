import { describe, expect, test } from 'bun:test';
import { initEditor } from '../../resources/ts/alpine/postMeta/lazyEditor';

function setGlobal(name: string, value: unknown): void {
    Object.defineProperty(globalThis, name, { configurable: true, value });
}

describe('initEditor', () => {
    test('does nothing and reports failure when TinyMCE is not available yet', () => {
        setGlobal('tinymce', undefined);
        setGlobal('tinyMCEPreInit', { mceInit: { teksttv_content: {} } });
        expect(initEditor()).toBe(false);
    });

    test('leaves an already-initialized editor untouched', () => {
        const calls: unknown[] = [];
        setGlobal('tinymce', {
            get: () => ({ id: 'teksttv_content' }),
            init: (config: unknown) => calls.push(config),
        });
        setGlobal('tinyMCEPreInit', { mceInit: { teksttv_content: { selector: '#teksttv_content' } } });
        expect(initEditor()).toBe(true);
        expect(calls).toEqual([]);
    });

    test('reports failure when WordPress has not published the editor config yet', () => {
        setGlobal('tinymce', { get: () => null, init: () => {} });
        setGlobal('tinyMCEPreInit', { mceInit: {} });
        expect(initEditor()).toBe(false);
    });

    test('initializes from the stored WordPress config when the editor is absent', () => {
        const config = { selector: '#teksttv_content', toolbar1: 'bold,teksttv_separator' };
        const calls: unknown[] = [];
        let editor: { id: string } | null = null;
        setGlobal('tinymce', {
            get: () => editor,
            init: (c: unknown) => {
                calls.push(c);
                editor = { id: 'teksttv_content' };
            },
        });
        setGlobal('tinyMCEPreInit', { mceInit: { teksttv_content: config } });
        expect(initEditor()).toBe(true);
        // Initialized with WordPress' own config (not a synthesized one).
        expect(calls).toEqual([config]);
    });
});
