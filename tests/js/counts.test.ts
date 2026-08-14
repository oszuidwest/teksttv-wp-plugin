import { afterEach, describe, expect, test } from 'bun:test';
import { updateTeksttvWordCount } from '../../resources/ts/alpine/postMeta/counts';
import type { TeksttvPostConfig } from '../../resources/ts/modules/types';

const originalDocument = globalThis.document;
const originalHTMLElement = globalThis.HTMLElement;

function renderWordCount(pageSeparator: boolean): string {
    globalThis.HTMLElement = class {
        innerHTML = '';
        textContent = '';
    } as unknown as typeof HTMLElement;

    const wordCount = new globalThis.HTMLElement();
    globalThis.document = {
        querySelector(selector: string) {
            return selector === '#teksttv-wordcount' ? wordCount : null;
        },
    } as unknown as Document;

    updateTeksttvWordCount({ pageSeparator } as TeksttvPostConfig, false, 'Eerste slide\n---\nTweede slide');

    return wordCount.innerHTML;
}

afterEach(() => {
    globalThis.document = originalDocument;
    globalThis.HTMLElement = originalHTMLElement;
});

describe('updateTeksttvWordCount', () => {
    test('shows the slide count when page separators are enabled', () => {
        expect(renderWordCount(true)).toBe('<span>4 woorden</span> · <span>2 slides</span>');
    });

    test('keeps the content on one slide when page separators are disabled', () => {
        expect(renderWordCount(false)).toBe('<span>5 woorden</span>');
    });
});
