import { describe, expect, test } from 'bun:test';
import { bindTeksttvEditorChanges } from '../../resources/ts/alpine/postMeta/editorContent';
import type { WPTinyMCEEditor } from '../../resources/ts/modules/types';

describe('bindTeksttvEditorChanges', () => {
    test('refreshes on TinyMCE keyboard input', () => {
        const listeners = new Map<string, () => void>();
        const editor = {
            on(eventNames: string, callback: () => void) {
                for (const eventName of eventNames.split(' ')) {
                    listeners.set(eventName, callback);
                }
            },
            fire(eventName: string) {
                listeners.get(eventName)?.();
            },
        } as WPTinyMCEEditor;
        let refreshes = 0;

        bindTeksttvEditorChanges(editor, () => {
            refreshes++;
        });
        editor.fire('keyup');
        expect(refreshes).toBe(1);
    });
});
