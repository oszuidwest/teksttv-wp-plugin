import { afterEach, describe, expect, test } from 'bun:test';
import { buildSlidesFromDom } from '../../resources/ts/alpine/postMeta/buildSlides';
import type { TeksttvPostConfig } from '../../resources/ts/modules/types';
import { setGlobal } from './setGlobal';

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
    setGlobal('document', originalDocument);
    setGlobal('HTMLElement', originalHTMLElement);
});

describe('buildSlidesFromDom', () => {
    test('preserves inline hyphens in a single slide', () => {
        setGlobal('document', editorDocument('<p>foo---bar</p>'));
        setGlobal('HTMLElement', class {} as typeof HTMLElement);

        const slides = buildSlidesFromDom(undefined, null);

        expect(slides).toHaveLength(1);
        expect(slides[0]).toMatchObject({ type: 'text', body: '<p>foo---bar</p>' });
    });

    test('does not split preview pages when the feature is disabled', () => {
        const content = '<p>Pagina één</p><p>---</p><p>Pagina twee</p>';
        setGlobal('document', editorDocument(content));
        setGlobal('HTMLElement', class {} as typeof HTMLElement);

        const slides = buildSlidesFromDom({ pageSeparator: false } as TeksttvPostConfig, null);

        expect(slides).toHaveLength(1);
        expect(slides[0]).toMatchObject({ type: 'text', body: content });
    });
});
