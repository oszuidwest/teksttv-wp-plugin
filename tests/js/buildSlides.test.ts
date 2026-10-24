import { afterEach, beforeEach, describe, expect, test } from 'bun:test';
import { buildSlidesFromDom } from '../../resources/ts/alpine/postMeta/buildSlides';
import type { TeksttvPostConfig } from '../../resources/ts/modules/types';

const originalDocument = globalThis.document;
const originalHTMLElement = globalThis.HTMLElement;

function editorDocument(content: string, fields: Record<string, string> = {}): Document {
    return {
        querySelector(selector: string) {
            if (selector === '#teksttv_content') return { value: content };
            return selector in fields ? { value: fields[selector] } : null;
        },
    } as unknown as Document;
}

function setEditorStore(title: unknown): void {
    (globalThis as { wp?: unknown }).wp = {
        data: { select: () => ({ getEditedPostAttribute: () => title }), subscribe: () => () => {} },
    };
}

beforeEach(() => {
    setEditorStore(undefined);
});

afterEach(() => {
    globalThis.document = originalDocument;
    globalThis.HTMLElement = originalHTMLElement;
});

describe('buildSlidesFromDom', () => {
    test('preserves inline hyphens in a single slide', () => {
        globalThis.document = editorDocument('<p>foo---bar</p>', { '#title': 'Klassieke kop' });
        globalThis.HTMLElement = class {} as typeof HTMLElement;

        const slides = buildSlidesFromDom(undefined, null);

        expect(slides).toHaveLength(1);
        // Without a block editor title, the classic title field is used.
        expect(slides[0]).toMatchObject({ type: 'text', title: 'Klassieke kop', body: '<p>foo---bar</p>' });
    });

    test('does not split preview pages when the feature is disabled', () => {
        const content = '<p>Pagina één</p><p>---</p><p>Pagina twee</p>';
        globalThis.document = editorDocument(content);
        globalThis.HTMLElement = class {} as typeof HTMLElement;

        const slides = buildSlidesFromDom({ pageSeparator: false } as TeksttvPostConfig, null);

        expect(slides).toHaveLength(1);
        expect(slides[0]).toMatchObject({ type: 'text', body: content });
    });

    test('takes the title from the block editor store', () => {
        globalThis.document = editorDocument('<p>Tekst</p>', { '#title': 'Klassieke kop' });
        globalThis.HTMLElement = class {} as typeof HTMLElement;
        setEditorStore('  Gutenberg kop ');

        const slides = buildSlidesFromDom({ fallbackTitle: 'Opgeslagen kop' } as TeksttvPostConfig, null);

        expect(slides[0]).toMatchObject({ title: 'Gutenberg kop' });
    });
});
