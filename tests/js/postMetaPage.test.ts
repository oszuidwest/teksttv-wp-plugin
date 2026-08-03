import { afterEach, describe, expect, test } from 'bun:test';
import { createPostMetaPage } from '../../resources/ts/alpine/postMetaPage';

const originalDocument = globalThis.document;
const originalWindow = globalThis.window;
const originalHTMLElement = globalThis.HTMLElement;
const globals = globalThis as Record<string, unknown>;

afterEach(() => {
    globalThis.document = originalDocument;
    globalThis.window = originalWindow;
    globalThis.HTMLElement = originalHTMLElement;
    delete globals.tinymce;
});

describe('createPostMetaPage', () => {
    test('init binds keyup on the TinyMCE editor and refreshes the word count', () => {
        class FakeElement {}
        globalThis.HTMLElement = FakeElement as unknown as typeof HTMLElement;

        const wordCount = new FakeElement() as HTMLElement;
        wordCount.innerHTML = '';

        const elements: Record<string, unknown> = {
            '#teksttv-active': { checked: true },
            '#teksttv-fields': { style: { removeProperty() {} } },
            '#teksttv-toggle-status': {},
            '#teksttv-wordcount': wordCount,
        };
        globalThis.document = {
            querySelector: (selector: string) => elements[selector] ?? null,
            getElementById: () => null,
            addEventListener() {},
        } as unknown as Document;

        globalThis.window = {
            setTimeout(fn: () => void) {
                fn();
                return 0;
            },
        } as unknown as Window & typeof globalThis;

        let boundEvents = '';
        let onEditorChange: (() => void) | undefined;
        const editor = {
            isHidden: () => false,
            getContent: () => '<p>Twee woorden</p>',
            on(events: string, callback: () => void) {
                boundEvents = events;
                onEditorChange = callback;
            },
        };
        globals.tinymce = {
            get: (id: string) => (id === 'teksttv_content' ? editor : null),
            on() {},
        };

        createPostMetaPage().init();

        expect(boundEvents.split(' ')).toContain('keyup');

        // init's own kick-off already ran `updatePreview`; reset so the
        // assertion proves the editor callback refreshed the count.
        wordCount.innerHTML = '';
        onEditorChange?.();

        expect(wordCount.innerHTML).toContain('2 woorden');
    });
});
