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
            '#teksttv-active': { checked: true, addEventListener() {} },
            '#teksttv-fields': { style: { removeProperty() {} } },
            '#teksttv-toggle-status': {},
            '#teksttv-wordcount': wordCount,
        };
        globalThis.document = {
            querySelector: (selector: string) => elements[selector] ?? null,
            getElementById: () => null,
            addEventListener() {},
        } as unknown as Document;

        const timers: Array<() => void> = [];
        globalThis.window = {
            setTimeout(fn: () => void) {
                timers.push(fn);
                return timers.length;
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

        onEditorChange?.();
        for (const timer of timers.splice(0)) timer();

        expect(wordCount.innerHTML).toContain('2 woorden');
    });
});
