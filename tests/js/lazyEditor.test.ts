import { describe, expect, test } from 'bun:test';
import { initEditor } from '../../resources/ts/alpine/postMeta/lazyEditor';

function setGlobal(name: string, value: unknown): void {
    Object.defineProperty(globalThis, name, { configurable: true, value });
}

describe('initEditor', () => {
    test('does nothing and reports failure when TinyMCE is not available yet', () => {
        setGlobal('tinymce', undefined);
        expect(initEditor()).toBe(false);
    });

    test('leaves an already-initialized editor untouched', () => {
        const calls: string[] = [];
        setGlobal('tinymce', {
            get: () => ({ id: 'teksttv_content' }),
            execCommand: (cmd: string) => calls.push(cmd),
        });
        expect(initEditor()).toBe(true);
        expect(calls).toEqual([]);
    });

    test('initializes the editor via mceAddEditor when it is not present', () => {
        const calls: Array<[string, boolean, unknown]> = [];
        let editor: { id: string } | null = null;
        setGlobal('tinymce', {
            // Absent until mceAddEditor runs, then present.
            get: () => editor,
            execCommand: (cmd: string, ui: boolean, value: unknown) => {
                calls.push([cmd, ui, value]);
                if (cmd === 'mceAddEditor') editor = { id: 'teksttv_content' };
            },
        });
        expect(initEditor()).toBe(true);
        expect(calls).toEqual([['mceAddEditor', false, 'teksttv_content']]);
    });
});
