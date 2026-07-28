import { afterEach, describe, expect, test } from 'bun:test';
import { buildSlidesFromDom } from '../../resources/ts/alpine/postMeta/buildSlides';

const originalDocument = globalThis.document;
const originalHTMLElement = globalThis.HTMLElement;

function editorDocument(content: string): Document {
    return {
        querySelector(selector: string) {
            return selector === '#teksttv_content' ? { value: content } : null;
        },
    } as unknown as Document;
}

afterEach(() => {
    globalThis.document = originalDocument;
    globalThis.HTMLElement = originalHTMLElement;
});

describe('buildSlidesFromDom', () => {
    test('preserves inline hyphens in a single slide', () => {
        globalThis.document = editorDocument('<p>foo---bar</p>');
        globalThis.HTMLElement = class {} as typeof HTMLElement;

        const slides = buildSlidesFromDom(undefined, null);

        expect(slides).toHaveLength(1);
        expect(slides[0]).toMatchObject({ type: 'text', body: '<p>foo---bar</p>' });
    });
});
